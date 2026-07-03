/**
 * E2E tests: invoice payment capture → treasury
 *
 * Pins the payment-capture contract on the sale/purchase create forms:
 *   - an untouched payment section records no payment at all
 *   - an entered cash amount records a payment whose treasury movement
 *     lands in the default (shared) cash account when none is chosen
 *   - an explicitly selected account wins over the default
 *
 * CustomSelect uses .custom-select__* classes; dropdowns teleport to <body>.
 */

let defaultCashId;
let secondCashId;

before(() => {
    Cypress.session.clearAllSavedSessions();
    cy.refreshDatabase();
    cy.tenantLogin();

    cy.php(`
        $tenant = App\\Models\\Tenant::where('slug', 'cypress')->first();
        app()->instance('currentTenant', $tenant);

        App\\Models\\Customer::factory()->create(['name' => 'Capture Customer']);
        App\\Models\\Supplier::factory()->create(['name' => 'Capture Supplier']);

        App\\Models\\Product::factory()
            ->has(App\\Models\\Unit::factory()->state(['name' => 'piece', 'conversion_factor' => 1]), 'units')
            ->create(['name' => 'Capture Product']);

        $defaultCash = App\\Models\\TreasuryAccount::create([
            'tenant_id' => $tenant->id,
            'name' => 'Default Cash',
            'type' => 'cash',
            'opening_balance' => 0,
            'currency' => 'SDG',
            'is_active' => true,
        ]);

        $secondCash = App\\Models\\TreasuryAccount::create([
            'tenant_id' => $tenant->id,
            'name' => 'Second Drawer',
            'type' => 'cash',
            'opening_balance' => 0,
            'currency' => 'SDG',
            'is_active' => true,
        ]);

        return ['defaultCashId' => $defaultCash->id, 'secondCashId' => $secondCash->id];
    `).then((result) => {
        defaultCashId = result.defaultCashId;
        secondCashId = result.secondCashId;
    });
});

beforeEach(() => {
    cy.tenantLogin();
});

/**
 * Fill party, product, unit, quantity, and price on the invoice form.
 */
function fillInvoiceLines(partyName, quantity, price) {
    // Party — first CustomSelect on the page
    cy.get('.custom-select').first().find('.custom-select__trigger').click();
    cy.get('.custom-select__dropdown .custom-select__search-input').type(partyName);
    cy.get('.custom-select__dropdown .custom-select__option').first().click();
    cy.get('.custom-select__dropdown').should('not.exist');

    // Product — second CustomSelect
    cy.get('.custom-select').eq(1).find('.custom-select__trigger').click();
    cy.get('.custom-select__dropdown .custom-select__search-input').type('Capture Product');
    cy.get('.custom-select__dropdown .custom-select__option').first().click();
    cy.get('.custom-select__dropdown').should('not.exist');

    // Unit — third CustomSelect
    cy.get('.custom-select').eq(2).find('.custom-select__trigger').click();
    cy.get('.custom-select__dropdown .custom-select__option').first().click();
    cy.get('.custom-select__dropdown').should('not.exist');

    cy.get('input[type="number"][min="0.01"]').first().clear().type(String(quantity));
    cy.get('input[type="number"][step="0.01"]').eq(1).clear().type(String(price));
}

function latestInvoiceState() {
    return cy.php(`
        $tenant = App\\Models\\Tenant::where('slug', 'cypress')->first();
        app()->instance('currentTenant', $tenant);

        $invoice = App\\Models\\Invoice::latest('id')->first();
        $payment = App\\Models\\Payment::where('invoice_id', $invoice->id)->latest('id')->first();
        $movement = $payment
            ? App\\Models\\TreasuryMovement::withoutGlobalScopes()
                ->where('movable_type', App\\Models\\Payment::class)
                ->where('movable_id', $payment->id)
                ->first()
            : null;

        return [
            'paidAmount' => $invoice->paid_amount,
            'paymentCount' => App\\Models\\Payment::where('invoice_id', $invoice->id)->count(),
            'paymentAccountId' => $payment?->treasury_account_id,
            'movementAmount' => $movement?->amount,
            'movementAccountId' => $movement?->treasury_account_id,
        ];
    `);
}

describe('Invoice payment capture', () => {
    it('records no payment when the payment section is untouched', () => {
        cy.visit('/purchases/create');

        fillInvoiceLines('Capture Supplier', 2, 100);

        cy.contains('button', 'Complete Purchase').click();
        cy.url().should('not.include', '/create');

        latestInvoiceState().then((state) => {
            expect(state.paidAmount).to.eq(0);
            expect(state.paymentCount).to.eq(0);
        });
    });

    it('sends an entered cash payment to the default cash account', () => {
        cy.visit('/purchases/create');

        fillInvoiceLines('Capture Supplier', 2, 100);

        // Enter the payment amount but leave the treasury account untouched.
        cy.get('#initial_payment').clear().type('200');

        cy.contains('button', 'Complete Purchase').click();
        cy.url().should('not.include', '/create');

        latestInvoiceState().then((state) => {
            expect(state.paidAmount).to.eq(200);
            expect(state.paymentCount).to.eq(1);
            expect(state.paymentAccountId).to.eq(defaultCashId);
            expect(state.movementAccountId).to.eq(defaultCashId);
            expect(state.movementAmount).to.eq(-20000); // purchase debits, minor units
        });
    });

    it('honours an explicitly selected treasury account on a cash sale', () => {
        cy.visit('/sales/create');

        fillInvoiceLines('Capture Customer', 1, 150);

        cy.get('#initial_payment').clear().type('150');

        // Treasury account — the CustomSelect under the "Received Into" label
        cy.contains('label', 'Received Into')
            .parent()
            .find('.custom-select__trigger')
            .click();
        cy.get('.custom-select__dropdown').contains('.custom-select__option', 'Second Drawer').click();
        cy.get('.custom-select__dropdown').should('not.exist');

        cy.contains('button', 'Complete Sale').click();
        cy.url().should('not.include', '/create');

        latestInvoiceState().then((state) => {
            expect(state.paymentAccountId).to.eq(secondCashId);
            expect(state.movementAccountId).to.eq(secondCashId);
            expect(state.movementAmount).to.eq(15000); // sale credits, minor units
        });
    });
});
