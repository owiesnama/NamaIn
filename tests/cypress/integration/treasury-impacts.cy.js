/**
 * E2E tests: treasury balance impacts from invoices, returns, and cheque clearing
 *
 * Verifies that cash sale/purchase invoices, returns with refunds,
 * and cheque clearing all produce correct treasury movements.
 */

const OPENING_BALANCE_CENTS = 100000; // SDG 1,000.00

let cashAccountId;
let bankAccountId;
let bankId;

before(() => {
    Cypress.session.clearAllSavedSessions();
    cy.refreshDatabase();
    cy.tenantLogin();

    cy.php(`
        $tenant = App\\Models\\Tenant::where('slug', 'cypress')->first();
        app()->instance('currentTenant', $tenant);

        App\\Models\\Customer::factory()->create(['name' => 'Treasury Test Customer']);
        App\\Models\\Supplier::factory()->create(['name' => 'Treasury Test Supplier']);

        $product = App\\Models\\Product::factory()
            ->has(App\\Models\\Unit::factory()->state(['name' => 'piece', 'conversion_factor' => 1]), 'units')
            ->create(['name' => 'Treasury Test Product', 'cost' => 500, 'price' => 1000]);

        $storage = App\\Models\\Storage::factory()->create(['type' => 'warehouse']);
        $storage->addStock($product, 200, 'Test setup stock');

        $cashAccount = App\\Models\\TreasuryAccount::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Cash',
            'type' => 'cash',
            'opening_balance' => ${OPENING_BALANCE_CENTS},
            'currency' => 'SDG',
            'is_active' => true,
        ]);

        $bank = App\\Models\\Bank::firstOrCreate(
            ['name' => 'Test Bank'],
            ['tenant_id' => $tenant->id]
        );

        $bankAccount = App\\Models\\TreasuryAccount::create([
            'tenant_id' => $tenant->id,
            'name' => 'Test Bank Account',
            'type' => 'bank',
            'opening_balance' => ${OPENING_BALANCE_CENTS},
            'currency' => 'SDG',
            'is_active' => true,
            'bank_id' => $bank->id,
        ]);

        App\\Models\\TreasuryAccount::firstOrCreate(
            ['tenant_id' => $tenant->id, 'type' => 'cheque_clearing'],
            ['name' => 'Cheques in Hand', 'opening_balance' => 0, 'currency' => 'SDG', 'is_active' => true]
        );

        return [
            'cashAccountId' => $cashAccount->id,
            'bankAccountId' => $bankAccount->id,
            'bankId' => $bank->id,
        ];
    `).then((result) => {
        cashAccountId = result.cashAccountId;
        bankAccountId = result.bankAccountId;
        bankId = result.bankId;
    });
});

beforeEach(() => {
    cy.tenantLogin();
});

// ---------------------------------------------------------------------------
// SALE → TREASURY
// ---------------------------------------------------------------------------

describe('Sale Invoice impacts treasury', () => {
    it('cash sale credits the treasury account', () => {
        cy.php(`
            $tenant = App\\Models\\Tenant::where('slug', 'cypress')->first();
            app()->instance('currentTenant', $tenant);

            $customer = App\\Models\\Customer::where('name', 'Treasury Test Customer')->first();
            $product = App\\Models\\Product::where('name', 'Treasury Test Product')->first();
            $unit = $product->units->first();
            $storage = App\\Models\\Storage::first();

            $action = app(App\\Actions\\StoreInvoiceAction::class);
            $action->handle(collect([
                'invocable' => ['id' => $customer->id, 'type' => 'App\\\\Models\\\\Customer', 'name' => $customer->name],
                'products' => [['product' => $product->id, 'unit' => $unit->id, 'quantity' => 5, 'price' => 10, 'storage' => $storage->id]],
                'total' => 50,
                'discount' => 0,
                'payment_method' => 'cash',
                'initial_payment_amount' => 50,
                'treasury_account_id' => ${cashAccountId},
            ]));

            $account = App\\Models\\TreasuryAccount::find(${cashAccountId});
            $lastMovement = $account->movements()->where('reason', 'payment_received')->latest('id')->first();

            return [
                'balance' => $account->currentBalance(),
                'lastReason' => $lastMovement?->reason?->value,
                'lastAmount' => $lastMovement?->amount,
            ];
        `).then((result) => {
            expect(result.balance).to.eq(OPENING_BALANCE_CENTS + 5000);
            expect(result.lastReason).to.eq('payment_received');
            expect(result.lastAmount).to.eq(5000);
        });
    });
});

// ---------------------------------------------------------------------------
// PURCHASE → TREASURY
// ---------------------------------------------------------------------------

describe('Purchase Invoice impacts treasury', () => {
    it('cash purchase debits the treasury account', () => {
        cy.php(`
            $tenant = App\\Models\\Tenant::where('slug', 'cypress')->first();
            app()->instance('currentTenant', $tenant);

            $supplier = App\\Models\\Supplier::where('name', 'Treasury Test Supplier')->first();
            $product = App\\Models\\Product::where('name', 'Treasury Test Product')->first();
            $unit = $product->units->first();
            $storage = App\\Models\\Storage::first();

            $action = app(App\\Actions\\StoreInvoiceAction::class);
            $action->handle(collect([
                'invocable' => ['id' => $supplier->id, 'type' => 'App\\\\Models\\\\Supplier', 'name' => $supplier->name],
                'products' => [['product' => $product->id, 'unit' => $unit->id, 'quantity' => 3, 'price' => 5, 'storage' => $storage->id]],
                'total' => 15,
                'discount' => 0,
                'payment_method' => 'cash',
                'initial_payment_amount' => 15,
                'treasury_account_id' => ${cashAccountId},
            ]));

            $account = App\\Models\\TreasuryAccount::find(${cashAccountId});
            $lastMovement = $account->movements()->where('reason', 'expense_paid')->latest('id')->first();

            return [
                'lastAmount' => $lastMovement?->amount,
                'lastReason' => $lastMovement?->reason?->value,
            ];
        `).then((result) => {
            expect(result.lastAmount).to.eq(-1500);
            expect(result.lastReason).to.eq('expense_paid');
        });
    });
});

// ---------------------------------------------------------------------------
// SALE RETURN → REFUND PAYMENT
// ---------------------------------------------------------------------------

describe('Sale Return records refund payment', () => {
    it('returning a sale creates an inverse invoice with refund payment', () => {
        cy.php(`
            $tenant = App\\Models\\Tenant::where('slug', 'cypress')->first();
            app()->instance('currentTenant', $tenant);

            $customer = App\\Models\\Customer::where('name', 'Treasury Test Customer')->first();
            $product = App\\Models\\Product::where('name', 'Treasury Test Product')->first();
            $unit = $product->units->first();
            $storage = App\\Models\\Storage::first();

            $storeAction = app(App\\Actions\\StoreInvoiceAction::class);
            $invoice = $storeAction->handle(collect([
                'invocable' => ['id' => $customer->id, 'type' => 'App\\\\Models\\\\Customer', 'name' => $customer->name],
                'products' => [['product' => $product->id, 'unit' => $unit->id, 'quantity' => 2, 'price' => 20, 'storage' => $storage->id]],
                'total' => 40,
                'discount' => 0,
                'payment_method' => 'cash',
                'treasury_account_id' => ${cashAccountId},
            ]));

            $returnAction = app(App\\Actions\\CreateInverseInvoiceAction::class);
            $inverse = $returnAction->handle($invoice, collect([
                'products' => $invoice->transactions->map(fn ($t) => [
                    'transaction_id' => $t->id,
                    'product_id' => $t->product_id,
                    'quantity' => $t->quantity,
                    'unit_id' => $t->unit_id,
                    'price' => $t->price,
                ])->toArray(),
                'total' => 40,
                'refund_amount' => 40,
                'payment_method' => 'cash',
            ]), 'Customer returned items');

            $refundPayment = $inverse->payments()->first();

            return [
                'isInverse' => (bool) $inverse->is_inverse,
                'hasRefundPayment' => !is_null($refundPayment),
                'refundAmount' => (float) $refundPayment?->amount,
                'refundMethod' => $refundPayment?->payment_method?->value,
            ];
        `).then((result) => {
            expect(result.isInverse).to.be.true;
            expect(result.hasRefundPayment).to.be.true;
            expect(result.refundAmount).to.eq(40);
            expect(result.refundMethod).to.eq('cash');
        });
    });
});

// ---------------------------------------------------------------------------
// PURCHASE RETURN → REFUND PAYMENT
// ---------------------------------------------------------------------------

describe('Purchase Return records refund payment', () => {
    it('returning a purchase creates an inverse invoice with refund payment', () => {
        cy.php(`
            $tenant = App\\Models\\Tenant::where('slug', 'cypress')->first();
            app()->instance('currentTenant', $tenant);

            $supplier = App\\Models\\Supplier::where('name', 'Treasury Test Supplier')->first();
            $product = App\\Models\\Product::where('name', 'Treasury Test Product')->first();
            $unit = $product->units->first();
            $storage = App\\Models\\Storage::first();

            $storeAction = app(App\\Actions\\StoreInvoiceAction::class);
            $invoice = $storeAction->handle(collect([
                'invocable' => ['id' => $supplier->id, 'type' => 'App\\\\Models\\\\Supplier', 'name' => $supplier->name],
                'products' => [['product' => $product->id, 'unit' => $unit->id, 'quantity' => 2, 'price' => 15, 'storage' => $storage->id]],
                'total' => 30,
                'discount' => 0,
                'payment_method' => 'cash',
                'treasury_account_id' => ${cashAccountId},
            ]));

            $returnAction = app(App\\Actions\\CreateInverseInvoiceAction::class);
            $inverse = $returnAction->handle($invoice, collect([
                'products' => $invoice->transactions->map(fn ($t) => [
                    'transaction_id' => $t->id,
                    'product_id' => $t->product_id,
                    'quantity' => $t->quantity,
                    'unit_id' => $t->unit_id,
                    'price' => $t->price,
                ])->toArray(),
                'total' => 30,
                'refund_amount' => 30,
                'payment_method' => 'cash',
            ]), 'Defective items returned');

            $refundPayment = $inverse->payments()->first();

            return [
                'isInverse' => (bool) $inverse->is_inverse,
                'hasRefundPayment' => !is_null($refundPayment),
                'refundAmount' => (float) $refundPayment?->amount,
                'refundMethod' => $refundPayment?->payment_method?->value,
            ];
        `).then((result) => {
            expect(result.isInverse).to.be.true;
            expect(result.hasRefundPayment).to.be.true;
            expect(result.refundAmount).to.eq(30);
            expect(result.refundMethod).to.eq('cash');
        });
    });
});

// ---------------------------------------------------------------------------
// CHEQUE CLEARING → TREASURY (both directions)
// ---------------------------------------------------------------------------

describe('Cheque clearing impacts treasury', () => {
    it('clearing a receivable cheque debits clearing account and credits bank account', () => {
        cy.php(`
            $tenant = App\\Models\\Tenant::where('slug', 'cypress')->first();
            app()->instance('currentTenant', $tenant);

            $customer = App\\Models\\Customer::where('name', 'Treasury Test Customer')->first();
            $bank = App\\Models\\Bank::where('name', 'Test Bank')->first();

            $clearingAccount = App\\Models\\TreasuryAccount::where('tenant_id', $tenant->id)
                ->where('type', 'cheque_clearing')->first();
            $bankAccount = App\\Models\\TreasuryAccount::find(${bankAccountId});

            // Register a receivable cheque (type=1) — this CREDITS the clearing account
            $cheque = app(App\\Actions\\RegisterChequeAction::class)->handle([
                'type' => 1,
                'payee_id' => $customer->id,
                'payee_type' => App\\Models\\Customer::class,
                'bank_id' => $bank->id,
                'reference_number' => 'CHQ-RCV-001',
                'amount' => 500,
                'due' => now()->addDays(30)->toDateString(),
                'notes' => 'Receivable cheque test',
            ], auth()->user());

            $clearingAfterRegister = $clearingAccount->currentBalance();
            $bankBefore = $bankAccount->currentBalance();

            return [
                'chequeId' => $cheque->id,
                'clearingAfterRegister' => $clearingAfterRegister,
                'bankBefore' => $bankBefore,
                'clearingAccountId' => $clearingAccount->id,
            ];
        `).then((result) => {
            // Clearing account should have been credited by 50000 cents on registration
            expect(result.clearingAfterRegister).to.eq(50000);

            // Now clear the cheque — debits clearing, credits bank
            cy.php(`
                $cheque = App\\Models\\Cheque::find(${result.chequeId});
                app(App\\Actions\\ClearCheque::class)->handle($cheque);

                $clearingAccount = App\\Models\\TreasuryAccount::find(${result.clearingAccountId});
                $bankAccount = App\\Models\\TreasuryAccount::find(${bankAccountId});

                return [
                    'clearingBalance' => $clearingAccount->currentBalance(),
                    'bankBalance' => $bankAccount->currentBalance(),
                    'chequeStatus' => $cheque->fresh()->status->value,
                ];
            `).then((cleared) => {
                expect(cleared.chequeStatus).to.eq('cleared');
                // Clearing: +50000 (register) - 50000 (clear) = 0
                expect(cleared.clearingBalance).to.eq(0);
                // Bank: opening + 50000
                expect(cleared.bankBalance).to.eq(result.bankBefore + 50000);
            });
        });
    });

    it('clearing a payable cheque debits the bank account', () => {
        cy.php(`
            $tenant = App\\Models\\Tenant::where('slug', 'cypress')->first();
            app()->instance('currentTenant', $tenant);

            $supplier = App\\Models\\Supplier::where('name', 'Treasury Test Supplier')->first();
            $bank = App\\Models\\Bank::where('name', 'Test Bank')->first();
            $bankAccount = App\\Models\\TreasuryAccount::find(${bankAccountId});
            $bankBefore = $bankAccount->currentBalance();

            // Payable cheque (type=0) — no clearing account impact on registration
            $cheque = app(App\\Actions\\RegisterChequeAction::class)->handle([
                'type' => 0,
                'payee_id' => $supplier->id,
                'payee_type' => App\\Models\\Supplier::class,
                'bank_id' => $bank->id,
                'reference_number' => 'CHQ-PAY-001',
                'amount' => 300,
                'due' => now()->addDays(30)->toDateString(),
                'notes' => 'Payable cheque test',
            ], auth()->user());

            return [
                'chequeId' => $cheque->id,
                'bankBefore' => $bankBefore,
            ];
        `).then((result) => {
            cy.php(`
                $cheque = App\\Models\\Cheque::find(${result.chequeId});
                app(App\\Actions\\ClearCheque::class)->handle($cheque);

                $bankAccount = App\\Models\\TreasuryAccount::find(${bankAccountId});

                return [
                    'bankBalance' => $bankAccount->currentBalance(),
                    'chequeStatus' => $cheque->fresh()->status->value,
                    'lastReason' => $bankAccount->movements()->latest('id')->first()?->reason?->value,
                ];
            `).then((cleared) => {
                expect(cleared.chequeStatus).to.eq('cleared');
                // Bank debited by 30000 cents
                expect(cleared.bankBalance).to.eq(result.bankBefore - 30000);
                expect(cleared.lastReason).to.eq('cheque_cleared');
            });
        });
    });
});

// ---------------------------------------------------------------------------
// TREASURY SHOW PAGE — verify movements visible in UI
// ---------------------------------------------------------------------------

describe('Treasury movements are recorded correctly', () => {
    it('cash account has both payment_received and expense_paid movements', () => {
        cy.php(`
            $account = App\\Models\\TreasuryAccount::find(${cashAccountId});
            $reasons = $account->movements()->pluck('reason')->map(fn ($r) => $r->value)->unique()->values()->toArray();

            return [
                'reasons' => $reasons,
                'movementCount' => $account->movements()->count(),
                'balance' => $account->currentBalance(),
            ];
        `).then((result) => {
            expect(result.movementCount).to.be.gte(2);
            expect(result.reasons).to.include('payment_received');
            expect(result.reasons).to.include('expense_paid');
        });
    });
});
