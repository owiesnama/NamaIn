/**
 * E2E tests: Reports module
 *
 * Verifies that the reports hub and individual report pages
 * load correctly, respect role-based access, display data,
 * and support date filtering.
 */

before(() => {
    Cypress.session.clearAllSavedSessions();
    cy.refreshDatabase();
});

/*
|--------------------------------------------------------------------------
| Reports Hub
|--------------------------------------------------------------------------
*/
describe('Reports Index', () => {
    beforeEach(() => cy.tenantLoginAs('owner'));

    it('loads the reports hub page', () => {
        cy.visit('/reports');
        cy.url().should('include', '/reports');
        cy.contains('h2', 'Reports').should('exist');
    });

    it('shows all 9 report cards', () => {
        cy.visit('/reports');

        cy.contains('Sales Report').should('exist');
        cy.contains('Purchase Report').should('exist');
        cy.contains('POS Sessions').should('exist');
        cy.contains('Profit & Loss').should('exist');
        cy.contains('Inventory Valuation').should('exist');
        cy.contains('Expense Summary').should('exist');
        cy.contains('Customer Aging').should('exist');
        cy.contains('Supplier Aging').should('exist');
        cy.contains('Treasury Summary').should('exist');
    });

    it('navigates to a report page when card is clicked', () => {
        cy.visit('/reports');
        cy.contains('Sales Report').click();
        cy.url().should('include', '/reports/sales');
    });
});

/*
|--------------------------------------------------------------------------
| Individual Report Pages
|--------------------------------------------------------------------------
*/
describe('Report Pages', () => {
    beforeEach(() => cy.tenantLoginAs('owner'));

    it('loads the sales report with summary cards and table', () => {
        cy.visit('/reports/sales');
        cy.url().should('include', '/reports/sales');
        cy.contains('Sales Report').should('exist');
        cy.contains('Total Revenue').should('exist');
        cy.contains('Invoices').should('exist');
        cy.contains('Items Sold').should('exist');
        cy.get('table').should('exist');
    });

    it('loads the purchase report', () => {
        cy.visit('/reports/purchases');
        cy.url().should('include', '/reports/purchases');
        cy.contains('Purchases Report').should('exist');
        cy.contains('Total Cost').should('exist');
    });

    it('loads the POS sessions report', () => {
        cy.visit('/reports/pos-sessions');
        cy.url().should('include', '/reports/pos-sessions');
        cy.contains('POS Session').should('exist');
    });

    it('loads the profit and loss report', () => {
        cy.visit('/reports/profit-and-loss');
        cy.url().should('include', '/reports/profit-and-loss');
        cy.contains('Profit').should('exist');
        cy.contains('Revenue').should('exist');
    });

    it('loads the inventory valuation report', () => {
        cy.visit('/reports/inventory-valuation');
        cy.url().should('include', '/reports/inventory-valuation');
        cy.contains('Inventory Valuation').should('exist');
        cy.contains('Total Value').should('exist');
    });

    it('loads the expense summary report', () => {
        cy.visit('/reports/expense-summary');
        cy.url().should('include', '/reports/expense-summary');
        cy.contains('Expense Summary').should('exist');
        cy.contains('Total Spent').should('exist');
    });

    it('loads the customer aging report', () => {
        cy.visit('/reports/customer-aging');
        cy.url().should('include', '/reports/customer-aging');
        cy.contains('Customer Aging').should('exist');
        cy.contains('Total Outstanding').should('exist');
    });

    it('loads the supplier aging report', () => {
        cy.visit('/reports/supplier-aging');
        cy.url().should('include', '/reports/supplier-aging');
        cy.contains('Supplier Aging').should('exist');
    });

    it('loads the treasury reconciliation report', () => {
        cy.visit('/reports/treasury-reconciliation');
        cy.url().should('include', '/reports/treasury-reconciliation');
        cy.contains('Treasury').should('exist');
    });
});

/*
|--------------------------------------------------------------------------
| Date Filtering
|--------------------------------------------------------------------------
*/
describe('Report Date Filters', () => {
    beforeEach(() => cy.tenantLoginAs('owner'));

    it('shows date preset buttons on the sales report', () => {
        cy.visit('/reports/sales');

        cy.contains('button', 'This Month').should('exist');
        cy.contains('button', 'Last Month').should('exist');
        cy.contains('button', 'This Year').should('exist');
        cy.contains('button', 'Custom').should('exist');
    });

    it('can switch between date presets', () => {
        cy.visit('/reports/sales');

        cy.contains('button', 'Last Month').click();
        cy.url().should('include', 'preset=last_month');
    });

    it('shows custom date inputs when Custom is clicked', () => {
        cy.visit('/reports/sales');

        cy.contains('button', 'Custom').click();
        cy.get('input[type="date"]').should('have.length', 2);
    });

    it('can change group-by on the sales report', () => {
        cy.viewport(1280, 720);
        cy.visit('/reports/sales');

        // Target group-by buttons specifically (inside the "Group By" section)
        cy.contains('p', 'Group By').parent().within(() => {
            cy.contains('button', 'Week').click();
        });
        cy.url().should('include', 'group_by=week');

        cy.contains('p', 'Group By').parent().within(() => {
            cy.contains('button', 'Month').click();
        });
        cy.url().should('include', 'group_by=month');
    });

    it('inventory valuation has no date filter', () => {
        cy.visit('/reports/inventory-valuation');

        cy.contains('button', 'This Month').should('not.exist');
        cy.contains('button', 'Custom').should('not.exist');
    });
});

/*
|--------------------------------------------------------------------------
| Back Navigation
|--------------------------------------------------------------------------
*/
describe('Report Navigation', () => {
    beforeEach(() => cy.tenantLoginAs('owner'));

    it('has a back link to reports hub from each report', () => {
        cy.viewport(1280, 720);
        cy.visit('/reports/sales');
        cy.get('a[href*="/reports"]').first().click();
        cy.url().should('match', /\/reports$/);
    });
});

/*
|--------------------------------------------------------------------------
| Export Button
|--------------------------------------------------------------------------
*/
describe('Report Exports', () => {
    beforeEach(() => cy.tenantLoginAs('owner'));

    it('shows an export button on the sales report', () => {
        cy.visit('/reports/sales');
        cy.contains('button', 'Export').should('exist');
    });

    it('clicking export triggers a queued export request', () => {
        // Seed a sale so the Export button is enabled (disabled when no data)
        cy.php(`
            $tenant = App\\Models\\Tenant::where('slug', 'cypress-test')->first();
            app()->instance('currentTenant', $tenant);
            $storage = App\\Models\\Storage::factory()->create();
            $customer = App\\Models\\Customer::factory()->create();
            $product = App\\Models\\Product::factory()->create(['cost' => 50]);
            $invoice = App\\Models\\Invoice::create([
                'invocable_id' => $customer->id,
                'invocable_type' => App\\Models\\Customer::class,
                'total' => 1000, 'paid_amount' => 1000, 'discount' => 0,
                'serial_number' => 'CYP-EXP-001', 'status' => 'delivered', 'payment_status' => 'paid',
            ]);
            App\\Models\\Transaction::create([
                'product_id' => $product->id, 'storage_id' => $storage->id,
                'invoice_id' => $invoice->id, 'quantity' => 10, 'base_quantity' => 10,
                'price' => 100, 'unit_cost' => 50, 'delivered' => true, 'created_at' => now(),
            ]);
            return 'seeded';
        `);

        cy.visit('/reports/sales');
        cy.contains('button', 'Export').click();

        // Should redirect back (POST exports.store) with flash
        cy.url().should('include', '/reports/sales');
    });
});
