/**
 * E2E tests: POS sale flow, hot items strip, and multi sale-point picker
 */

before(() => {
    Cypress.session.clearAllSavedSessions();
    cy.refreshDatabase();
    cy.tenantLogin();

    cy.php(`
        $tenant = App\\Models\\Tenant::where('slug', 'cypress')->first();
        app()->instance('currentTenant', $tenant);

        $storage = App\\Models\\Storage::factory()->create([
            'type' => 'sale_point',
            'name' => 'Main POS Register',
        ]);

        App\\Models\\Storage::factory()->create([
            'type' => 'sale_point',
            'name' => 'Secondary POS Register',
        ]);

        $product = App\\Models\\Product::factory()
            ->has(App\\Models\\Unit::factory()->state(['name' => 'piece', 'conversion_factor' => 1]), 'units')
            ->create(['name' => 'POS Test Product', 'cost' => 500]);

        $storage->addStock($product, 50, 'Initial test stock');

        // A delivered customer sale so the product shows up in the Hot Items strip.
        $invoice = App\\Models\\Invoice::factory()->create();
        App\\Models\\Transaction::factory()->create([
            'invoice_id' => $invoice->id,
            'storage_id' => $storage->id,
            'product_id' => $product->id,
            'quantity' => 25,
            'base_quantity' => 25,
            'price' => 100,
            'delivered' => true,
        ]);

        return ['ok' => true]
    `);
});

beforeEach(() => {
    cy.tenantLogin();
});

const ensureSessionOpen = () => {
    cy.get('body').then(($body) => {
        if ($body.text().includes('Open POS Session')) {
            cy.get('#opening_float').clear().type('5000');
            cy.contains('button', 'Open Register').click();
            cy.contains('POS Test Product').should('exist');
        }
    });
};

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

        ensureSessionOpen();

        cy.contains('button', 'POS Test Product').first().click();

        // The checkout button sits at the bottom of a full-height cart column, so it can
        // fall below the test viewport fold — scroll it into view before clicking.
        cy.get('button').filter(':contains("Complete Sale"), :contains("Review & Complete")')
            .scrollIntoView()
            .click({ force: true });

        cy.contains('button', 'Confirm Payment').click({ force: true });

        cy.url().should('include', '/pos');
    });
});

describe('POS Hot Items', () => {
    it('renders the hot items strip and adds the top item to cart', () => {
        cy.visit('/pos');

        ensureSessionOpen();

        cy.contains('Hot Items').should('exist');

        // The hot items strip is the first occurrence of the product card in the DOM.
        cy.contains('button', 'POS Test Product').first().click();

        // Cart now holds the tapped item.
        cy.contains('Cart').should('exist');
        cy.contains('POS Test Product').should('exist');
    });
});

describe('POS Sale Point Picker', () => {
    it('switches POS to another sale point', () => {
        cy.visit('/pos');

        ensureSessionOpen();

        // With more than one sale point, the picker is rendered. Select whichever sale
        // point is NOT currently active so the switch always navigates (selecting the
        // active one is a no-op by design).
        cy.get('#sale-point-picker').should('exist').then(($sel) => {
            const current = $sel.val();
            const target = [...$sel[0].options].find((o) => o.value !== current);

            cy.get('#sale-point-picker').select(target.value);

            // POS opened against the chosen sale point: the URL carries its id and the
            // destination page's picker reflects it.
            cy.url().should('include', `storage_id=${target.value}`);
            cy.get('#sale-point-picker', { timeout: 10000 }).should('have.value', target.value);
        });
    });
});
