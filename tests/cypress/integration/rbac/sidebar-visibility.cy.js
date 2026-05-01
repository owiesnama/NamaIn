/**
 * E2E tests: sidebar navigation visibility per role
 *
 * Verifies that each role sees only the sidebar links their
 * permissions allow. NavLinks use v-if so unpermitted links
 * are removed from the DOM entirely.
 */

before(() => {
    Cypress.session.clearAllSavedSessions();
    cy.refreshDatabase();
});

/*
|--------------------------------------------------------------------------
| Sidebar link text → permission mapping (from AppLayout.vue)
|--------------------------------------------------------------------------
|
| Operations group  (pos.view || pos.operate || inventory.transfer)
|   POS               pos.view || pos.operate
|   POS History        pos.view
|   Stock Transfers    inventory.transfer
|
| Inventory group   (products.view || inventory.view)
|   Products           products.view
|   Storages           inventory.view
|
| Relations group   (customers.view || suppliers.view)
|   Customers          customers.view
|   Suppliers          suppliers.view
|
| Accounting group  (sales.view || purchases.view || payments.view || payments.manage-cheques || expenses.view || treasury.view)
|   Sales              sales.view
|   Purchases          purchases.view
|   Payments           payments.view
|   Cheques            payments.manage-cheques
|   Expenses           expenses.view
|   Treasury           treasury.view
|
| Team group        (users.view || roles.manage)
|   Team Members       users.view
|   Roles              roles.manage
|
*/

describe('Sidebar visibility', () => {

    // ── Owner ────────────────────────────────────────────────
    describe('as owner', () => {
        beforeEach(() => cy.tenantLoginAs('owner'));

        it('shows all sidebar links', () => {
            cy.visit('/dashboard');

            cy.get('aside').within(() => {
                // Operations
                cy.contains('POS').should('exist');
                cy.contains('POS History').should('exist');
                cy.contains('Stock Transfers').should('exist');

                // Inventory
                cy.contains('Products').should('exist');
                cy.contains('Storages').should('exist');

                // Relations
                cy.contains('Customers').should('exist');
                cy.contains('Suppliers').should('exist');

                // Accounting
                cy.contains('Sales').should('exist');
                cy.contains('Purchases').should('exist');
                cy.contains('Payments').should('exist');
                cy.contains('Cheques').should('exist');
                cy.contains('Expenses').should('exist');
                cy.contains('Treasury').should('exist');

                // Team
                cy.contains('Team Members').should('exist');
                cy.contains('Roles').should('exist');
            });
        });
    });

    // ── Manager ──────────────────────────────────────────────
    // Has all permissions except users.assign-role and roles.manage
    describe('as manager', () => {
        beforeEach(() => cy.tenantLoginAs('manager'));

        it('shows permitted links', () => {
            cy.visit('/dashboard');

            cy.get('aside').within(() => {
                cy.contains('POS').should('exist');
                cy.contains('POS History').should('exist');
                cy.contains('Stock Transfers').should('exist');
                cy.contains('Products').should('exist');
                cy.contains('Storages').should('exist');
                cy.contains('Customers').should('exist');
                cy.contains('Suppliers').should('exist');
                cy.contains('Sales').should('exist');
                cy.contains('Purchases').should('exist');
                cy.contains('Payments').should('exist');
                cy.contains('Cheques').should('exist');
                cy.contains('Expenses').should('exist');
                cy.contains('Treasury').should('exist');
                cy.contains('Team Members').should('exist');
            });
        });

        it('hides Roles link (requires roles.manage)', () => {
            cy.visit('/dashboard');

            cy.get('aside').within(() => {
                cy.contains('Roles').should('not.exist');
            });
        });
    });

    // ── Cashier ──────────────────────────────────────────────
    // products.view, customers.view, suppliers.view, sales.view,
    // sales.create, pos.view, pos.operate, pos.manage-sessions,
    // payments.view, payments.create, inventory.view
    describe('as cashier', () => {
        beforeEach(() => cy.tenantLoginAs('cashier'));

        it('shows permitted links', () => {
            cy.visit('/dashboard');

            cy.get('aside').within(() => {
                cy.contains('POS').should('exist');
                cy.contains('POS History').should('exist');
                cy.contains('Products').should('exist');
                cy.contains('Storages').should('exist');
                cy.contains('Customers').should('exist');
                cy.contains('Suppliers').should('exist');
                cy.contains('Sales').should('exist');
                cy.contains('Payments').should('exist');
            });
        });

        it('hides links requiring unpermitted permissions', () => {
            cy.visit('/dashboard');

            cy.get('aside').within(() => {
                // No inventory.transfer
                cy.contains('Stock Transfers').should('not.exist');

                // No purchases.view
                cy.contains('Purchases').should('not.exist');

                // No payments.manage-cheques
                cy.contains('Cheques').should('not.exist');

                // No expenses.view
                cy.contains('Expenses').should('not.exist');

                // No treasury.view
                cy.contains('Treasury').should('not.exist');

                // No users.view
                cy.contains('Team Members').should('not.exist');

                // No roles.manage
                cy.contains('Roles').should('not.exist');
            });
        });
    });

    // ── Staff ────────────────────────────────────────────────
    // products.view, customers.view, suppliers.view, sales.view,
    // purchases.view, inventory.view, expenses.view, payments.view,
    // treasury.view
    describe('as staff', () => {
        beforeEach(() => cy.tenantLoginAs('staff'));

        it('shows permitted links', () => {
            cy.visit('/dashboard');

            cy.get('aside').within(() => {
                cy.contains('Products').should('exist');
                cy.contains('Storages').should('exist');
                cy.contains('Customers').should('exist');
                cy.contains('Suppliers').should('exist');
                cy.contains('Sales').should('exist');
                cy.contains('Purchases').should('exist');
                cy.contains('Payments').should('exist');
                cy.contains('Expenses').should('exist');
                cy.contains('Treasury').should('exist');
            });
        });

        it('hides links requiring unpermitted permissions', () => {
            cy.visit('/dashboard');

            cy.get('aside').within(() => {
                // No pos.view / pos.operate
                cy.contains('POS').should('not.exist');
                cy.contains('POS History').should('not.exist');

                // No inventory.transfer
                cy.contains('Stock Transfers').should('not.exist');

                // No payments.manage-cheques
                cy.contains('Cheques').should('not.exist');

                // No users.view
                cy.contains('Team Members').should('not.exist');

                // No roles.manage
                cy.contains('Roles').should('not.exist');
            });
        });
    });
});
