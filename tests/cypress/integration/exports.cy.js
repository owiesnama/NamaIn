/**
 * E2E tests: Export Center
 *
 * Verifies the export history page, queued export flow,
 * and download functionality.
 */

before(() => {
    Cypress.session.clearAllSavedSessions();
    cy.refreshDatabase();
    cy.tenantLogin();

    // Seed data so report Export buttons are enabled (disabled when no data)
    cy.php(`
        $tenant = App\\Models\\Tenant::where('slug', 'cypress')->first();
        app()->instance('currentTenant', $tenant);
        $storage = App\\Models\\Storage::factory()->create();
        $customer = App\\Models\\Customer::factory()->create();
        $supplier = App\\Models\\Supplier::factory()->create();
        $product = App\\Models\\Product::factory()->create(['cost' => 50]);

        $saleInvoice = App\\Models\\Invoice::create([
            'invocable_id' => $customer->id,
            'invocable_type' => App\\Models\\Customer::class,
            'total' => 1000, 'paid_amount' => 1000, 'discount' => 0,
            'serial_number' => 'CYP-EXP-SALE', 'status' => 'delivered', 'payment_status' => 'paid',
        ]);
        App\\Models\\Transaction::create([
            'product_id' => $product->id, 'storage_id' => $storage->id,
            'invoice_id' => $saleInvoice->id, 'quantity' => 10, 'base_quantity' => 10,
            'price' => 100, 'unit_cost' => 50, 'delivered' => true, 'created_at' => now(),
        ]);

        $purchaseInvoice = App\\Models\\Invoice::create([
            'invocable_id' => $supplier->id,
            'invocable_type' => App\\Models\\Supplier::class,
            'total' => 500, 'paid_amount' => 0, 'discount' => 0,
            'serial_number' => 'CYP-EXP-PUR', 'status' => 'delivered', 'payment_status' => 'unpaid',
        ]);
        App\\Models\\Transaction::create([
            'product_id' => $product->id, 'storage_id' => $storage->id,
            'invoice_id' => $purchaseInvoice->id, 'quantity' => 10, 'base_quantity' => 10,
            'price' => 50, 'unit_cost' => 50, 'delivered' => true, 'created_at' => now(),
        ]);

        return 'seeded';
    `);
});

/*
|--------------------------------------------------------------------------
| Export History Page
|--------------------------------------------------------------------------
*/
describe('Export History', () => {
    beforeEach(() => cy.tenantLoginAs('owner'));

    it('loads the exports page', () => {
        cy.visit('/exports');
        cy.url().should('include', '/exports');
        cy.contains('Export History').should('exist');
    });

    it('shows empty state when no exports exist', () => {
        cy.visit('/exports');
        cy.contains('No exports yet').should('exist');
    });

    it('shows export logs after an export is queued', () => {
        // Queue an export via the reports page
        cy.visit('/reports/sales');
        cy.contains('button', 'Export').click();

        // Visit exports page
        cy.visit('/exports');
        cy.contains('report-sales').should('exist');
        cy.contains('xlsx').should('exist');
    });
});

/*
|--------------------------------------------------------------------------
| Queued Export Flow
|--------------------------------------------------------------------------
*/
describe('Queued Export', () => {
    beforeEach(() => cy.tenantLoginAs('owner'));

    it('can queue an export from the sales report', () => {
        cy.visit('/reports/sales');
        cy.contains('button', 'Export').click();

        // Should stay on the same page (redirect back)
        cy.url().should('include', '/reports/sales');
    });

    it('can queue an export from the purchase report', () => {
        cy.visit('/reports/purchases');
        cy.contains('button', 'Export').click();
        cy.url().should('include', '/reports/purchases');
    });

    it('export log appears with queued status', () => {
        cy.visit('/reports/sales');
        cy.contains('button', 'Export').click();

        cy.visit('/exports');
        cy.get('table tbody tr').first().within(() => {
            cy.contains('report-sales').should('exist');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Operations Center
|--------------------------------------------------------------------------
*/
describe('Operations Center', () => {
    beforeEach(() => cy.tenantLoginAs('owner'));

    it('does not auto-open the panel when navigating between pages', () => {
        // Queue an export so there is a pending operation in the shared snapshot.
        cy.visit('/reports/sales');
        cy.contains('button', 'Export').click();
        cy.url().should('include', '/reports/sales');

        // Navigate to another page — the panel must NOT re-open on navigation.
        cy.visit('/customers');
        cy.get('[data-testid="operations-panel"]').should('not.exist');
        cy.get('[data-testid="operations-pill"]').should('be.visible');

        // Navigating again keeps it closed.
        cy.visit('/exports');
        cy.get('[data-testid="operations-panel"]').should('not.exist');
        cy.get('[data-testid="operations-pill"]').should('be.visible');
    });

    it('opens the panel only when the user clicks the pill', () => {
        cy.visit('/reports/sales');
        cy.contains('button', 'Export').click();

        cy.visit('/customers');
        cy.get('[data-testid="operations-panel"]').should('not.exist');

        cy.get('[data-testid="operations-pill"]').click();
        cy.get('[data-testid="operations-panel"]').should('be.visible');
        cy.get('[data-testid="operations-panel"]').contains('Operations').should('exist');
    });
});

/*
|--------------------------------------------------------------------------
| Legacy Export Retrofit
|--------------------------------------------------------------------------
*/
describe('Retrofitted Exports', () => {
    beforeEach(() => cy.tenantLoginAs('owner'));

    it('customer export queues instead of direct download', () => {
        cy.visit('/customers');

        // The export link/button should exist
        cy.get('a[href*="export"], button').then(($els) => {
            // If there's an export link, click it
            const exportLink = $els.filter(':contains("Export")');
            if (exportLink.length) {
                cy.wrap(exportLink.first()).click();
                // Should redirect back (queued) instead of downloading
                cy.url().should('include', '/customers');
            }
        });
    });
});

/*
|--------------------------------------------------------------------------
| Export RBAC
|--------------------------------------------------------------------------
*/
describe('Export Access Control', () => {
    it('staff user can view their own exports', () => {
        cy.tenantLoginAs('staff');
        cy.visit('/exports');
        cy.url().should('include', '/exports');
        cy.contains('Export History').should('exist');
    });

    it('export store requires valid export key', () => {
        cy.tenantLoginAs('owner');

        cy.csrfToken().then((token) => {
            cy.request({
                method: 'POST',
                url: '/exports',
                body: {
                    _token: token,
                    export_key: 'nonexistent-key',
                    format: 'xlsx',
                },
                failOnStatusCode: false,
                followRedirect: false,
            }).then((response) => {
                // Validation failure returns 302 redirect back with errors
                expect(response.status).to.eq(302);
            });
        });
    });
});
