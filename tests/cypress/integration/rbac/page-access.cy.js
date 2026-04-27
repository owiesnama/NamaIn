/**
 * E2E tests: page access per role
 *
 * Verifies that full Inertia page visits either render correctly
 * for authorized roles or return 403 for unauthorized roles.
 *
 * Only backend-protected routes produce actual 403 responses.
 * Unprotected routes (Products, Suppliers, Sales, Purchases,
 * Expenses, Payments, Cheques, POS) are accessible to any
 * authenticated user regardless of role.
 */

before(() => {
    cy.refreshDatabase();
});

/*
|--------------------------------------------------------------------------
| Staff
|--------------------------------------------------------------------------
| View-only: products, customers, suppliers, sales, purchases,
| inventory, expenses, payments, treasury.
*/
describe('as staff', () => {
    beforeEach(() => cy.tenantLoginAs('staff'));

    // ── Allowed pages (all view permissions) ──

    it('can access /dashboard', () => {
        cy.visit('/dashboard');
        cy.url().should('include', '/dashboard');
    });

    it('can access /products', () => {
        cy.visit('/products');
        cy.url().should('include', '/products');
    });

    it('can access /customers', () => {
        cy.visit('/customers');
        cy.url().should('include', '/customers');
    });

    it('can access /suppliers', () => {
        cy.visit('/suppliers');
        cy.url().should('include', '/suppliers');
    });

    it('can access /sales', () => {
        cy.visit('/sales');
        cy.url().should('include', '/sales');
    });

    it('can access /purchases', () => {
        cy.visit('/purchases');
        cy.url().should('include', '/purchases');
    });

    it('can access /expenses', () => {
        cy.visit('/expenses');
        cy.url().should('include', '/expenses');
    });

    it('can access /payments', () => {
        cy.visit('/payments');
        cy.url().should('include', '/payments');
    });

    it('can access /treasury (has treasury.view)', () => {
        cy.visit('/treasury');
        cy.url().should('include', '/treasury');
    });

    // ── Blocked pages (backend-protected) ──

    it('cannot access /users (no users.view)', () => {
        cy.visit('/users', { failOnStatusCode: false });
        cy.get('body').should('contain', '403');
    });

    it('cannot access /roles (no roles.manage)', () => {
        cy.visit('/roles', { failOnStatusCode: false });
        cy.get('body').should('contain', '403');
    });

    it('cannot access /stock-transfers (no inventory.transfer)', () => {
        cy.visit('/stock-transfers', { failOnStatusCode: false });
        cy.get('body').should('contain', '403');
    });

    it('cannot access /treasury/create (no treasury.create)', () => {
        cy.visit('/treasury/create', { failOnStatusCode: false });
        cy.get('body').should('contain', '403');
    });

    it('cannot access /treasury/transfer (no treasury.transfer)', () => {
        cy.visit('/treasury/transfer', { failOnStatusCode: false });
        cy.get('body').should('contain', '403');
    });
});

/*
|--------------------------------------------------------------------------
| Cashier
|--------------------------------------------------------------------------
| products.view, customers.view, suppliers.view, sales.view,
| sales.create, pos.view, pos.operate, pos.manage-sessions,
| payments.view, payments.create, inventory.view
*/
describe('as cashier', () => {
    beforeEach(() => cy.tenantLoginAs('cashier'));

    // ── Allowed pages ──

    it('can access /dashboard', () => {
        cy.visit('/dashboard');
        cy.url().should('include', '/dashboard');
    });

    it('can access /products', () => {
        cy.visit('/products');
        cy.url().should('include', '/products');
    });

    it('can access /customers', () => {
        cy.visit('/customers');
        cy.url().should('include', '/customers');
    });

    it('can access /sales', () => {
        cy.visit('/sales');
        cy.url().should('include', '/sales');
    });

    // POS redirects to /storages when no sale point storage exists
    // in a fresh database — tested separately after seeding storages.

    it('can access /payments', () => {
        cy.visit('/payments');
        cy.url().should('include', '/payments');
    });

    // ── Blocked pages (backend-protected) ──

    it('cannot access /users (no users.view)', () => {
        cy.visit('/users', { failOnStatusCode: false });
        cy.get('body').should('contain', '403');
    });

    it('cannot access /roles (no roles.manage)', () => {
        cy.visit('/roles', { failOnStatusCode: false });
        cy.get('body').should('contain', '403');
    });

    it('cannot access /treasury (no treasury.view)', () => {
        cy.visit('/treasury', { failOnStatusCode: false });
        cy.get('body').should('contain', '403');
    });

    it('cannot access /stock-transfers (no inventory.transfer)', () => {
        cy.visit('/stock-transfers', { failOnStatusCode: false });
        cy.get('body').should('contain', '403');
    });
});

/*
|--------------------------------------------------------------------------
| Manager
|--------------------------------------------------------------------------
| All permissions except users.assign-role and roles.manage.
*/
describe('as manager', () => {
    beforeEach(() => cy.tenantLoginAs('manager'));

    // ── Allowed pages ──

    it('can access /dashboard', () => {
        cy.visit('/dashboard');
        cy.url().should('include', '/dashboard');
    });

    it('can access /users (has users.view)', () => {
        cy.visit('/users');
        cy.url().should('include', '/users');
    });

    it('can access /treasury (has treasury.view)', () => {
        cy.visit('/treasury');
        cy.url().should('include', '/treasury');
    });

    it('can access /treasury/create (has treasury.create)', () => {
        cy.visit('/treasury/create');
        cy.url().should('include', '/treasury/create');
    });

    it('can access /stock-transfers (has inventory.transfer)', () => {
        cy.visit('/stock-transfers');
        cy.url().should('include', '/stock-transfers');
    });

    // POS redirects to /storages when no sale point storage exists
    // in a fresh database — tested separately after seeding storages.

    // ── Blocked pages ──

    it('cannot access /roles (no roles.manage)', () => {
        cy.visit('/roles', { failOnStatusCode: false });
        cy.get('body').should('contain', '403');
    });
});
