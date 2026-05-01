/**
 * E2E tests: treasury account creation
 */

before(() => {
    Cypress.session.clearAllSavedSessions();
    cy.refreshDatabase();
});

beforeEach(() => {
    cy.tenantLogin();
});

describe('Treasury Accounts', () => {
    it('creates a cash treasury account', () => {
        cy.visit('/treasury/create');
        cy.url().should('include', '/treasury/create');

        cy.get('#name').type('Main Cash Drawer');
        cy.get('#type').select('cash');
        cy.get('#currency').clear().type('SDG');
        cy.get('#opening_balance').clear().type('10000');

        cy.contains('button', 'Create Account').click();

        cy.url().should('include', '/treasury');
        cy.url().should('not.include', '/create');
        cy.contains('Main Cash Drawer').should('exist');
    });

    it('creates a bank treasury account', () => {
        cy.visit('/treasury/create');

        cy.get('#name').type('CIB Bank Account');
        cy.get('#type').select('bank');
        cy.get('#currency').clear().type('SDG');
        cy.get('#opening_balance').clear().type('500000');

        cy.contains('button', 'Create Account').click();

        cy.url().should('include', '/treasury');
        cy.url().should('not.include', '/create');
        cy.contains('CIB Bank Account').should('exist');
    });
});
