/**
 * E2E tests: Reports sidebar navigation visibility
 *
 * Verifies that the Reports link appears in the sidebar for
 * roles with reports.view permission (owner, manager) and
 * is absent for roles without it (cashier, staff).
 */

before(() => {
    cy.refreshDatabase();
});

describe('Reports sidebar link visibility', () => {

    // ── Owner (has reports.view) ──
    it('shows Reports link for owner', () => {
        cy.tenantLoginAs('owner');
        cy.visit('/dashboard');

        cy.get('aside').within(() => {
            cy.contains('Reports').should('exist');
        });
    });

    it('owner can click Reports link and reach reports hub', () => {
        cy.tenantLoginAs('owner');
        cy.visit('/dashboard');

        cy.get('aside').within(() => {
            cy.contains('Reports').click();
        });

        cy.url().should('include', '/reports');
        cy.contains('h2', 'Reports').should('exist');
    });

    // ── Manager (has reports.view) ──
    it('shows Reports link for manager', () => {
        cy.tenantLoginAs('manager');
        cy.visit('/dashboard');

        cy.get('aside').within(() => {
            cy.contains('Reports').should('exist');
        });
    });

    // ── Cashier (no reports.view) ──
    it('does NOT show Reports link for cashier', () => {
        cy.tenantLoginAs('cashier');
        cy.visit('/dashboard');

        cy.get('aside').within(() => {
            cy.contains('Reports').should('not.exist');
        });
    });

    // ── Staff (no reports.view) ──
    it('does NOT show Reports link for staff', () => {
        cy.tenantLoginAs('staff');
        cy.visit('/dashboard');

        cy.get('aside').within(() => {
            cy.contains('Reports').should('not.exist');
        });
    });
});
