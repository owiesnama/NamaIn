/**
 * E2E tests: Reports RBAC
 *
 * Verifies that only users with reports.view permission can
 * access report pages. Staff and cashier roles should get 403.
 * Owner and manager roles should get 200.
 */

before(() => {
    cy.refreshDatabase();
});

const reportPaths = [
    '/reports',
    '/reports/sales',
    '/reports/purchases',
    '/reports/pos-sessions',
    '/reports/profit-and-loss',
    '/reports/inventory-valuation',
    '/reports/expense-summary',
    '/reports/customer-aging',
    '/reports/supplier-aging',
    '/reports/treasury-reconciliation',
];

/*
|--------------------------------------------------------------------------
| Staff (no reports.view)
|--------------------------------------------------------------------------
*/
describe('as staff', () => {
    beforeEach(() => cy.tenantLoginAs('staff'));

    reportPaths.forEach((path) => {
        it(`cannot access ${path} (403)`, () => {
            cy.visit(path, { failOnStatusCode: false });
            cy.get('body').should('contain', '403');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Cashier (no reports.view)
|--------------------------------------------------------------------------
*/
describe('as cashier', () => {
    beforeEach(() => cy.tenantLoginAs('cashier'));

    reportPaths.forEach((path) => {
        it(`cannot access ${path} (403)`, () => {
            cy.visit(path, { failOnStatusCode: false });
            cy.get('body').should('contain', '403');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Manager (has reports.view)
|--------------------------------------------------------------------------
*/
describe('as manager', () => {
    beforeEach(() => cy.tenantLoginAs('manager'));

    reportPaths.forEach((path) => {
        it(`can access ${path}`, () => {
            cy.visit(path);
            cy.url().should('include', path);
        });
    });
});

/*
|--------------------------------------------------------------------------
| Owner (has reports.view)
|--------------------------------------------------------------------------
*/
describe('as owner', () => {
    beforeEach(() => cy.tenantLoginAs('owner'));

    reportPaths.forEach((path) => {
        it(`can access ${path}`, () => {
            cy.visit(path);
            cy.url().should('include', path);
        });
    });
});

/*
|--------------------------------------------------------------------------
| Sidebar Visibility
|--------------------------------------------------------------------------
*/
describe('Reports sidebar link', () => {
    it('shows Reports link for owner', () => {
        cy.tenantLoginAs('owner');
        cy.visit('/dashboard');
        cy.get('aside').within(() => {
            cy.contains('Reports').should('exist');
        });
    });

    it('shows Reports link for manager', () => {
        cy.tenantLoginAs('manager');
        cy.visit('/dashboard');
        cy.get('aside').within(() => {
            cy.contains('Reports').should('exist');
        });
    });

    it('does not show Reports link for cashier', () => {
        cy.tenantLoginAs('cashier');
        cy.visit('/dashboard');
        cy.get('aside').within(() => {
            cy.contains('Reports').should('not.exist');
        });
    });

    it('does not show Reports link for staff', () => {
        cy.tenantLoginAs('staff');
        cy.visit('/dashboard');
        cy.get('aside').within(() => {
            cy.contains('Reports').should('not.exist');
        });
    });
});
