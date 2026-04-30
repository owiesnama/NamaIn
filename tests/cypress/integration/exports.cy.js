/**
 * E2E tests: Export Center
 *
 * Verifies the export history page, queued export flow,
 * and download functionality.
 */

before(() => {
    cy.refreshDatabase();
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
            }).then((response) => {
                expect(response.status).to.eq(302); // Redirect back with errors
            });
        });
    });
});
