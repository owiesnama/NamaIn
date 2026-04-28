/**
 * E2E tests: purchase invoice creation
 *
 * The purchase form starts with one empty line item by default.
 * CustomSelect uses .custom-select__* classes.
 * The dropdown is teleported to <body>, so we target it globally.
 */

before(() => {
    cy.refreshDatabase();
    cy.tenantLogin();

    cy.php(`
        $tenant = App\\Models\\Tenant::where('slug', 'cypress-test')->first();
        app()->instance('currentTenant', $tenant);

        App\\Models\\Supplier::factory()->create(['name' => 'Cypress Supplier']);

        App\\Models\\Product::factory()
            ->has(App\\Models\\Unit::factory()->state(['name' => 'piece', 'conversion_factor' => 1]), 'units')
            ->create(['name' => 'Cypress Purchase Product']);

        return ['ok' => true]
    `);
});

beforeEach(() => {
    cy.tenantLogin();
});

describe('Purchase Invoice', () => {
    it('creates a purchase invoice for a supplier', () => {
        cy.visit('/purchases/create');
        cy.url().should('include', '/purchases/create');

        // Select supplier — first CustomSelect on the page
        cy.get('.custom-select').first().find('.custom-select__trigger').click();
        cy.get('.custom-select__dropdown').should('have.length', 1);
        cy.get('.custom-select__dropdown .custom-select__search-input').type('Cypress Supplier');
        cy.get('.custom-select__dropdown .custom-select__option').first().click();

        // Wait for previous dropdown to close before opening the next
        cy.get('.custom-select__dropdown').should('not.exist');

        // First line item already exists — select product (second CustomSelect)
        cy.get('.custom-select').eq(1).find('.custom-select__trigger').click();
        cy.get('.custom-select__dropdown').should('have.length', 1);
        cy.get('.custom-select__dropdown .custom-select__search-input').type('Cypress Purchase');
        cy.get('.custom-select__dropdown .custom-select__option').first().click();

        cy.get('.custom-select__dropdown').should('not.exist');

        // Select unit (third CustomSelect — auto-populates after product selection)
        cy.get('.custom-select').eq(2).find('.custom-select__trigger').click();
        cy.get('.custom-select__dropdown').should('have.length', 1);
        cy.get('.custom-select__dropdown .custom-select__option').first().click();

        // Quantity (first number input in the line item area)
        cy.get('input[type="number"][min="0.01"]').first().clear().type('5');

        // Price (second number input in the line item area)
        cy.get('input[type="number"][step="0.01"]').eq(1).clear().type('1000');

        cy.contains('button', 'Complete Purchase').click();

        cy.url().should('include', '/purchases');
        cy.url().should('not.include', '/create');
    });
});
