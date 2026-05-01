/**
 * E2E tests: tenant security boundaries
 *
 * Validates that tenant isolation, authentication, and API
 * security are enforced end-to-end.
 */

describe('Tenant Security', () => {
    before(() => {
        Cypress.session.clearAllSavedSessions();
        cy.refreshDatabase();
    });

    it('API /api/customers requires authentication', () => {
        cy.request({
            url: '/api/customers',
            failOnStatusCode: false,
            followRedirect: false,
        }).then((response) => {
            expect(response.status).to.be.oneOf([401, 302]);
        });
    });

    it('unauthenticated tenant subdomain request redirects to login', () => {
        cy.request({
            url: '/dashboard',
            failOnStatusCode: false,
            followRedirect: false,
        }).then((response) => {
            expect(response.status).to.be.oneOf([302, 301]);
        });
    });

    it('authenticated user can access their tenant dashboard', () => {
        cy.tenantLogin();
        cy.request({
            url: '/dashboard',
            failOnStatusCode: false,
        }).its('status').should('eq', 200);
    });
});
