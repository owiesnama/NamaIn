/**
 * E2E tests: Reports with seeded data
 *
 * Seeds realistic data then verifies reports display
 * meaningful content (non-zero summaries, table rows).
 */

before(() => {
    Cypress.session.clearAllSavedSessions();
    cy.refreshDatabase();
    cy.tenantLogin();

    // Seed data for reports
    cy.php(`
        $tenant = App\\Models\\Tenant::where('slug', 'cypress')->first();
        app()->instance('currentTenant', $tenant);

        $storage = App\\Models\\Storage::factory()->create(['name' => 'Cypress Storage']);
        $customer = App\\Models\\Customer::factory()->create(['name' => 'Cypress Customer']);
        $supplier = App\\Models\\Supplier::factory()->create(['name' => 'Cypress Supplier']);
        $product = App\\Models\\Product::factory()->create(['name' => 'Cypress Product', 'cost' => 50]);

        // Create sales invoice with transactions
        $saleInvoice = App\\Models\\Invoice::create([
            'invocable_id' => $customer->id,
            'invocable_type' => App\\Models\\Customer::class,
            'total' => 5000,
            'paid_amount' => 2000,
            'discount' => 0,
            'serial_number' => 'CYP-SALE-001',
            'status' => 'delivered',
            'payment_status' => 'partially_paid',
        ]);

        App\\Models\\Transaction::create([
            'product_id' => $product->id,
            'storage_id' => $storage->id,
            'invoice_id' => $saleInvoice->id,
            'quantity' => 50,
            'base_quantity' => 50,
            'price' => 100,
            'unit_cost' => 50,
            'delivered' => true,
            'created_at' => now(),
        ]);

        // Create purchase invoice
        $purchaseInvoice = App\\Models\\Invoice::create([
            'invocable_id' => $supplier->id,
            'invocable_type' => App\\Models\\Supplier::class,
            'total' => 2500,
            'paid_amount' => 0,
            'discount' => 0,
            'serial_number' => 'CYP-PUR-001',
            'status' => 'delivered',
            'payment_status' => 'unpaid',
        ]);

        App\\Models\\Transaction::create([
            'product_id' => $product->id,
            'storage_id' => $storage->id,
            'invoice_id' => $purchaseInvoice->id,
            'quantity' => 50,
            'base_quantity' => 50,
            'price' => 50,
            'unit_cost' => 50,
            'delivered' => true,
            'created_at' => now(),
        ]);

        // Create stock via storage relationship
        $storage->stock()->attach($product->id, ['quantity' => 50]);

        // Create expense
        App\\Models\\Expense::create([
            'title' => 'Office Rent',
            'amount' => 1000,
            'currency' => 'SDG',
            'expensed_at' => now(),
            'status' => App\\Enums\\ExpenseStatus::Approved,
            'created_by' => App\\Models\\User::first()->id,
        ]);

        // Create treasury account with movement
        $treasury = App\\Models\\TreasuryAccount::factory()->create([
            'name' => 'Main Cash',
            'type' => 'cash',
            'opening_balance' => 100000,
        ]);

        App\\Models\\TreasuryMovement::create([
            'treasury_account_id' => $treasury->id,
            'amount' => 5000,
            'balance_after' => 105000,
            'reason' => App\\Enums\\TreasuryMovementReason::PaymentReceived,
            'occurred_at' => now(),
            'created_by' => App\\Models\\User::first()->id,
            'movable_id' => $saleInvoice->id,
            'movable_type' => App\\Models\\Invoice::class,
        ]);

        return ['ok' => true];
    `);
});

beforeEach(() => {
    cy.tenantLogin();
});

describe('Sales Report with data', () => {
    it('shows non-zero revenue in summary', () => {
        cy.visit('/reports/sales');
        cy.contains('Total Revenue').parent().invoke('text').should('not.match', /SDG\s*0\.00/);
    });

    it('shows data rows in the table', () => {
        cy.visit('/reports/sales');
        cy.get('table tbody tr').should('have.length.at.least', 1);
    });
});

describe('Purchase Report with data', () => {
    it('shows purchase data in summary', () => {
        cy.visit('/reports/purchases');
        cy.contains('Total Cost').should('exist');
        cy.contains('Items Purchased').should('exist');
    });

    it('shows data rows in the table', () => {
        cy.visit('/reports/purchases');
        cy.get('table tbody tr').should('have.length.at.least', 1);
    });
});

describe('Profit & Loss with data', () => {
    it('shows revenue, COGS, and expenses', () => {
        cy.visit('/reports/profit-and-loss');
        cy.contains('Revenue').should('exist');
        cy.contains('COGS').should('exist');
        cy.contains('Expenses').should('exist');
    });

    it('shows monthly breakdown rows', () => {
        cy.visit('/reports/profit-and-loss');
        cy.get('table tbody tr').should('have.length.at.least', 1);
    });
});

describe('Inventory Valuation with data', () => {
    it('shows products with stock', () => {
        cy.visit('/reports/inventory-valuation');
        // Verify table rows exist (product data is rendered in the table)
        cy.get('table tbody tr', { timeout: 10000 }).should('have.length.at.least', 1);
    });

    it('renders the product and storage names in the table', () => {
        cy.visit('/reports/inventory-valuation');
        // Product and Warehouse columns must render real names (not blank).
        cy.get('table tbody').contains('td', 'Cypress Product').should('be.visible');
        cy.get('table tbody').contains('td', 'Cypress Storage').should('be.visible');
    });

    it('renders a non-zero average cost cell', () => {
        cy.visit('/reports/inventory-valuation');
        // The Avg Cost column (4th cell) must not be zero.
        cy.get('table tbody tr')
            .first()
            .find('td')
            .eq(3)
            .invoke('text')
            .should('not.match', /SDG\s*0\.00/);
    });

    it('shows non-zero total value', () => {
        cy.visit('/reports/inventory-valuation');
        cy.contains('Total Value').parent().invoke('text').should('not.match', /SDG\s*0\.00/);
    });
});

describe('Customer Aging with data', () => {
    it('shows customers with outstanding balances', () => {
        cy.visit('/reports/customer-aging');
        // The customer name column must render the real name (not blank).
        cy.get('table tbody').contains('td', 'Cypress Customer').should('be.visible');
    });

    it('shows amounts in aging buckets', () => {
        cy.visit('/reports/customer-aging');
        cy.get('table tbody tr').should('have.length.at.least', 1);
    });
});

describe('Supplier Aging with data', () => {
    it('shows suppliers with outstanding balances', () => {
        cy.visit('/reports/supplier-aging');
        // The supplier name column must render the real name (not blank).
        cy.get('table tbody').contains('td', 'Cypress Supplier').should('be.visible');
    });
});

describe('Expense Summary with data', () => {
    it('shows expenses', () => {
        cy.visit('/reports/expense-summary');
        cy.contains('Total Spent').parent().invoke('text').should('not.match', /SDG\s*0\.00/);
    });
});

describe('Treasury Reconciliation with data', () => {
    it('shows treasury movements', () => {
        cy.visit('/reports/treasury-reconciliation');
        cy.get('table tbody tr').should('have.length.at.least', 1);
    });

    it('renders the account name and date in the table', () => {
        cy.visit('/reports/treasury-reconciliation');
        // Account (1st cell) and Date (2nd cell) columns must render, not be blank.
        cy.get('table tbody').contains('td', 'Main Cash').should('be.visible');
        cy.get('table tbody tr').first().find('td').eq(1).invoke('text').should('not.be.empty');
    });
});
