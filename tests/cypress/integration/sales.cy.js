/**
 * E2E tests: sale invoice creation
 *
 * CustomSelect dropdown is teleported to <body>, so we target it globally.
 */

before(() => {
    cy.refreshDatabase();
    cy.tenantLogin();

    cy.php(`
        $tenant = App\\Models\\Tenant::where('slug', 'cypress-test')->first();
        app()->instance('currentTenant', $tenant);

        App\\Models\\Customer::factory()->create(['name' => 'Cypress Customer']);

        $product = App\\Models\\Product::factory()
            ->has(App\\Models\\Unit::factory()->state(['name' => 'piece', 'conversion_factor' => 1]), 'units')
            ->create(['name' => 'Cypress Sale Product']);

        $storage = App\\Models\\Storage::factory()->create(['type' => 'warehouse']);
        $storage->addStock($product, 100, 'Test setup stock');

        return ['ok' => true]
    `);
});

beforeEach(() => {
    cy.tenantLogin();
});

describe('Sale Invoice', () => {
    it('creates a sale invoice for a customer', () => {
        cy.visit('/sales/create');
        cy.url().should('include', '/sales/create');

        cy.get('.custom-select').first().find('.custom-select__trigger').click();
        cy.get('.custom-select__dropdown').should('have.length', 1);
        cy.get('.custom-select__dropdown .custom-select__search-input').type('Cypress Customer');
        cy.get('.custom-select__dropdown .custom-select__option').first().click();

        cy.get('.custom-select__dropdown').should('not.exist');

        cy.get('.custom-select').eq(1).find('.custom-select__trigger').click();
        cy.get('.custom-select__dropdown').should('have.length', 1);
        cy.get('.custom-select__dropdown .custom-select__search-input').type('Cypress Sale');
        cy.get('.custom-select__dropdown .custom-select__option').first().click();

        cy.get('.custom-select__dropdown').should('not.exist');

        cy.get('.custom-select').eq(2).find('.custom-select__trigger').click();
        cy.get('.custom-select__dropdown').should('have.length', 1);
        cy.get('.custom-select__dropdown .custom-select__option').first().click();

        cy.get('input[type="number"][min="0.01"]').first().clear().type('3');
        cy.get('input[type="number"][step="0.01"]').eq(1).clear().type('2500');

        cy.contains('button', 'Complete Sale').click();

        cy.url().should('include', '/sales');
        cy.url().should('not.include', '/create');
    });
});
