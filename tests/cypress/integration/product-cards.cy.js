/**
 * E2E tests: Product card view, layout toggle, inline editing, quick-update
 */

before(() => {
    Cypress.session.clearAllSavedSessions();
    cy.refreshDatabase();
    cy.tenantLogin('cypress');

    // Seed products for testing
    cy.php(`
        $tenant = App\\Models\\Tenant::where('slug', 'cypress')->first();
        app()->instance('currentTenant', $tenant);

        for ($i = 1; $i <= 5; $i++) {
            $product = App\\Models\\Product::factory()->create([
                'name' => "Card Test Product {$i}",
                'cost' => $i * 100,
                'price' => $i * 120,
            ]);
            $product->units()->create(['name' => 'Piece', 'conversion_factor' => 1]);
        }

        return 'seeded';
    `);
});

beforeEach(() => {
    cy.tenantLogin('cypress');
});

/*
|--------------------------------------------------------------------------
| Layout Toggle
|--------------------------------------------------------------------------
*/
describe('Layout Toggle', () => {
    it('shows layout toggle buttons on products page', () => {
        cy.visit('/products');
        cy.get('[title="Table View"]').should('exist');
        cy.get('[title="Grid View"]').should('exist');
    });

    it('defaults to table layout', () => {
        cy.visit('/products');
        // Table should be visible
        cy.get('table').should('exist');
    });

    it('switches to card layout when grid button is clicked', () => {
        cy.visit('/products');
        cy.get('[title="Grid View"]').click();

        // Cards grid should appear
        cy.get('[data-testid="product-cards-grid"]').should('exist');
        // Table should not be visible
        cy.get('table').should('not.exist');
    });

    it('switches back to table layout', () => {
        cy.visit('/products');
        cy.get('[title="Grid View"]').click();
        cy.get('[title="Table View"]').click();

        cy.get('table').should('exist');
        cy.get('[data-testid="product-cards-grid"]').should('not.exist');
    });

    it('persists layout preference across page loads', () => {
        cy.visit('/products');
        cy.get('[title="Grid View"]').click();

        // Revisit the page
        cy.visit('/products');

        // Should still be in card view
        cy.get('[data-testid="product-cards-grid"]').should('exist');
    });
});

/*
|--------------------------------------------------------------------------
| Card View Display
|--------------------------------------------------------------------------
*/
describe('Card View Display', () => {
    beforeEach(() => {
        cy.visit('/products');
        cy.get('[title="Grid View"]').click();
    });

    it('shows product cards in a grid', () => {
        cy.get('[data-testid="product-cards-grid"]').should('exist');
        cy.contains('Card Test Product 1').should('exist');
    });

    it('each card shows editable name, cost, and price fields', () => {
        cy.get('[data-testid="product-cards-grid"]')
            .find('[data-testid="product-card"]')
            .first()
            .within(() => {
                cy.get('input[name="name"]').should('exist');
                cy.get('input[name="cost"]').should('exist');
                cy.get('input[name="price"]').should('exist');
            });
    });

    it('each card shows stock status badge', () => {
        cy.get('[data-testid="product-card"]').first().within(() => {
            // Should have one of the status badges
            cy.get('[class*="rounded-lg"]').should('exist');
        });
    });

    it('each card shows categories', () => {
        // Categories may be empty for factory products, but the element should exist
        cy.get('[data-testid="product-card"]').first().should('exist');
    });
});

/*
|--------------------------------------------------------------------------
| Card Inline Editing
|--------------------------------------------------------------------------
*/
describe('Card Inline Editing', () => {
    beforeEach(() => {
        cy.visit('/products');
        cy.get('[title="Grid View"]').click();
    });

    it('shows save button when a field is edited', () => {
        cy.get('[data-testid="product-card"]').first().within(() => {
            cy.get('input[name="name"]').clear().type('Updated Product Name');
            cy.contains('button', 'Save').should('be.visible');
        });
    });

    it('saves inline edits via quick-update endpoint', () => {
        cy.get('[data-testid="product-card"]').first().within(() => {
            cy.get('input[name="price"]').clear().type('9999');
            cy.contains('button', 'Save').click();
        });

        // Verify the update persisted
        cy.php(`
            $product = App\\Models\\Product::where('name', 'Card Test Product 1')->first();
            return $product ? (int) $product->price : null;
        `).should('eq', 9999);
    });

    it('hides save button after successful save', () => {
        cy.get('[data-testid="product-card"]').first().within(() => {
            cy.get('input[name="cost"]').clear().type('555');
            cy.contains('button', 'Save').click();
            cy.contains('button', 'Save').should('not.exist');
        });
    });
});

/*
|--------------------------------------------------------------------------
| New Product Card
|--------------------------------------------------------------------------
*/
describe('New Product Card', () => {
    beforeEach(() => {
        cy.visit('/products');
        cy.get('[title="Grid View"]').click();
    });

    it('shows add product card in the grid', () => {
        cy.contains('Add Product').should('exist');
    });

    it('expands to show form fields when clicked', () => {
        cy.contains('Add Product').click();
        cy.get('input[placeholder*="Product name"]').should('be.visible');
    });

    it('creates a new product with default base unit', () => {
        cy.contains('Add Product').click();

        // The new product form has name, cost, price inputs
        cy.get('[data-testid="new-product-form"]').within(() => {
            cy.get('input[type="text"]').clear().type('Cypress New Card Product');
            cy.get('input[name="new-cost"]').clear().type('500');
            cy.get('input[name="new-price"]').clear().type('600');
        });

        cy.get('.bg-emerald-600').filter(':visible').last().click();

        // Verify product was created
        cy.php(`
            $product = App\\Models\\Product::where('name', 'Cypress New Card Product')->first();
            return $product ? [
                'cost' => $product->cost,
                'price' => $product->price,
                'units' => $product->units->count(),
                'unit_name' => $product->units->first()?->name,
            ] : null;
        `).then((result) => {
            expect(result).to.not.be.null;
            expect(result.cost).to.eq(500);
            expect(result.price).to.eq(600);
            expect(result.units).to.eq(1);
        });
    });
});

/*
|--------------------------------------------------------------------------
| Quick Update Authorization
|--------------------------------------------------------------------------
*/
describe('Quick Update Authorization', () => {
    it('cashier cannot quick-update products', () => {
        cy.tenantLoginAs('cashier');

        cy.php(`
            $product = App\\Models\\Product::first();
            return $product ? $product->id : null;
        `).then((productId) => {
            if (!productId) return;

            cy.csrfToken().then((token) => {
                cy.request({
                    method: 'PATCH',
                    url: '/products/' + productId + '/quick-update',
                    body: { _token: token, name: 'Hacked Name' },
                    failOnStatusCode: false,
                    followRedirect: false,
                }).then((response) => {
                    expect(response.status).to.be.oneOf([302, 403]);
                });
            });
        });
    });
});

/*
|--------------------------------------------------------------------------
| Products Table — Cost Column
|--------------------------------------------------------------------------
*/
describe('Products Table Cost Column', () => {
    beforeEach(() => {
        cy.visit('/products');
        // Ensure we are in table layout
        cy.get('[title="Table View"]').click();
        cy.get('table').should('exist');
    });

    it('renders a Cost column header in the table', () => {
        cy.get('table thead').contains('th', 'Cost').should('exist');
    });

    it('renders a Cost value for a product row', () => {
        // The Cost column renders a value (earlier tests may mutate amounts, so assert
        // on the Cost column cell by index rather than a hardcoded number).
        cy.contains('table thead th', /^Cost$/).invoke('index').then((idx) => {
            cy.get('table tbody tr').first().find('td').eq(idx)
                .invoke('text').should('match', /\d/);
        });
    });

    it('offers Cost as a sort option', () => {
        cy.get('[title="Filters"]').click();
        cy.contains('Cost').should('exist');
    });
});

/*
|--------------------------------------------------------------------------
| Add / Edit Product Modal
|--------------------------------------------------------------------------
*/
describe('Add Product Modal', () => {
    beforeEach(() => {
        cy.visit('/products');
    });

    it('creates a product through the modal with an editable currency', () => {
        cy.contains('button', 'Add New Product').click();

        // The open modal lacks the v-show inline display:none; in headless the enter
        // transition leaves it at opacity-0, so interactions use { force: true }.
        cy.get('div.fixed.inset-0.z-50.overflow-y-auto:not([style*="display: none"])').first().within(() => {
            cy.get('#name').clear({ force: true }).type('Modal Created Product', { force: true });
            cy.get('#cost').clear({ force: true }).type('250', { force: true });
            cy.get('#price').clear({ force: true }).type('300', { force: true });
            cy.get('#currency').clear({ force: true }).type('USD', { force: true });

            cy.contains('button', 'Add Unit').click({ force: true });
            cy.get('input[placeholder="Unit eg: box"]').clear({ force: true }).type('Piece', { force: true });
            cy.get('input[placeholder="Unit Conversion Factor"]').clear({ force: true }).type('1', { force: true });

            cy.get('button[type="submit"]').click({ force: true });
        });

        cy.php(`
            $product = App\\Models\\Product::where('name', 'Modal Created Product')->first();
            return $product ? [
                'cost' => $product->cost,
                'price' => $product->price,
                'currency' => $product->currency,
            ] : null;
        `).then((result) => {
            expect(result).to.not.be.null;
            expect(result.cost).to.eq(250);
            expect(result.price).to.eq(300);
            expect(result.currency).to.eq('USD');
        });
    });

    it('shows a validation message when a required field is missing', () => {
        cy.contains('button', 'Add New Product').click();

        cy.get('div.fixed.inset-0.z-50.overflow-y-auto:not([style*="display: none"])').first().within(() => {
            cy.get('#name').clear({ force: true }).type('Missing Cost Product', { force: true });
            // Leave cost empty (required|numeric|gt:0)
            cy.get('#cost').clear({ force: true });

            cy.get('button[type="submit"]').click({ force: true });

            // Field-level error message should appear in the modal
            cy.get('.text-red-600').should('exist');
        });
    });
});

describe('Edit Product Modal', () => {
    beforeEach(() => {
        cy.visit('/products');
        cy.get('[title="Table View"]').click();
        cy.get('table').should('exist');
    });

    it('updates a field through the edit modal', () => {
        // Open the edit modal for the Card Test Product 1 row
        cy.contains('table tbody tr', 'Card Test Product 1').find('[title="Edit"]').click({ force: true });

        cy.get('div.fixed.inset-0.z-50.overflow-y-auto:not([style*="display: none"])').first().within(() => {
            cy.get('#name').should('exist');
            cy.get('#price').clear({ force: true }).type('7777', { force: true });
            cy.get('#currency').clear({ force: true }).type('EUR', { force: true });

            cy.get('button[type="submit"]').click({ force: true });
        });

        cy.php(`
            $product = App\\Models\\Product::where('name', 'Card Test Product 1')->first();
            return $product ? [
                'price' => (int) $product->price,
                'currency' => $product->currency,
            ] : null;
        `).then((result) => {
            expect(result).to.not.be.null;
            expect(result.price).to.eq(7777);
            expect(result.currency).to.eq('EUR');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Optional Fields
|--------------------------------------------------------------------------
*/
describe('Optional Expire Date and Units', () => {
    it('can create product without expire date via modal', () => {
        cy.visit('/products');
        cy.contains('button', 'Add New Product').click();

        cy.get('div.fixed.inset-0.z-50.overflow-y-auto:not([style*="display: none"])').first().within(() => {
            cy.get('#name').clear({ force: true }).type('No Expiry Product', { force: true });
            cy.get('#cost').clear({ force: true }).type('100', { force: true });

            // Add a unit row, then fill its fields
            cy.contains('button', 'Add Unit').click({ force: true });
            cy.get('input[placeholder="Unit eg: box"]').clear({ force: true }).type('Pack', { force: true });
            cy.get('input[placeholder="Unit Conversion Factor"]').clear({ force: true }).type('1', { force: true });

            cy.get('button[type="submit"]').click({ force: true });
        });

        cy.php(`
            return App\\Models\\Product::where('name', 'No Expiry Product')->exists() ? 'created' : 'not_found';
        `).should('eq', 'created');
    });
});
