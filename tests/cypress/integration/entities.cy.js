/**
 * E2E tests: entity creation (product, supplier, customer)
 */

before(() => {
    Cypress.session.clearAllSavedSessions();
    cy.refreshDatabase();
});

beforeEach(() => {
    cy.tenantLogin();
});

describe('Products', () => {
    it('creates a new product via the modal', () => {
        cy.visit('/products');
        cy.url().should('include', '/products');

        cy.contains('button', 'Add New Product').click();

        // Wait for modal to be fully rendered
        cy.contains('Add New Product').should('be.visible');

        cy.get('#name').clear().type('Cypress Test Product');
        cy.get('#cost').clear().type('1500');

        // Flatpickr: click to open calendar, pick a date from the open calendar
        cy.get('#expire_date').click();
        cy.get('.flatpickr-calendar.open').within(() => {
            cy.get('.flatpickr-next-month').click();
            cy.get('.flatpickr-day:not(.prevMonthDay):not(.nextMonthDay)').contains('15').click();
        });

        // Fill unit fields inside the modal
        cy.get('input[placeholder="Unit eg: box"]').clear().type('Box');
        cy.get('input[placeholder="Unit Conversion Factor"]').clear().type('1');

        // Green primary submit button in the modal footer
        cy.get('button.bg-emerald-600').filter(':visible').last().click();

        cy.contains('Cypress Test Product').should('exist');
    });
});

describe('Suppliers', () => {
    it('creates a new supplier via the modal', () => {
        cy.visit('/suppliers');
        cy.url().should('include', '/suppliers');

        cy.contains('button', 'Add New Supplier').click();

        cy.get('#name').type('Cypress Supplier Co.');
        cy.get('#address').type('123 Test Street, Khartoum');
        cy.get('#phone').type('0912345678');

        cy.get('button.bg-emerald-600').filter(':visible').last().click();

        cy.contains('Cypress Supplier Co.').should('exist');
    });
});

describe('Customers', () => {
    it('creates a new customer via the modal', () => {
        cy.visit('/customers');
        cy.url().should('include', '/customers');

        cy.contains('button', 'Add New').click();

        cy.get('#name').type('Cypress Customer');
        cy.get('#address').type('456 Main Road, Omdurman');
        cy.get('#phone').type('0987654321');

        cy.get('button.bg-emerald-600').filter(':visible').last().click();

        cy.contains('Cypress Customer').should('exist');
    });
});
