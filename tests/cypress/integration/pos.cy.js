/**
 * E2E tests: POS sale flow
 */

before(() => {
    cy.refreshDatabase();
    cy.tenantLogin();

    cy.php(`
        $tenant = App\\Models\\Tenant::where('slug', 'cypress-test')->first();
        app()->instance('currentTenant', $tenant);

        $storage = App\\Models\\Storage::factory()->create([
            'type' => 'sale_point',
            'name' => 'Main POS Register',
        ]);

        $product = App\\Models\\Product::factory()
            ->has(App\\Models\\Unit::factory()->state(['name' => 'piece', 'conversion_factor' => 1]), 'units')
            ->create(['name' => 'POS Test Product', 'cost' => 500]);

        $storage->addStock($product, 50, 'Initial test stock');

        return ['ok' => true];
    `);
});

beforeEach(() => {
    cy.tenantLogin();
});

describe('POS Sale Flow', () => {
    it('opens a POS session', () => {
        cy.visit('/pos');
        cy.url().should('include', '/pos');

        cy.contains('Open POS Session').should('exist');
        cy.get('#opening_float').clear().type('5000');
        cy.contains('button', 'Open Register').click();

        cy.contains('POS Test Product').should('exist');
    });

    it('adds a product to cart and checks out', () => {
        cy.visit('/pos');

        cy.get('body').then(($body) => {
            if ($body.text().includes('Open POS Session')) {
                cy.get('#opening_float').clear().type('5000');
                cy.contains('button', 'Open Register').click();
                cy.contains('POS Test Product').should('exist');
            }
        });

        cy.contains('button', 'POS Test Product').click();

        cy.get('button').filter(':contains("Complete Sale"), :contains("Review & Complete")').click();

        cy.contains('button', 'Confirm Payment').click();

        cy.url().should('include', '/pos');
    });
});
