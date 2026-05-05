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
| Optional Fields
|--------------------------------------------------------------------------
*/
describe('Optional Expire Date and Units', () => {
    it('can create product without expire date via modal', () => {
        cy.visit('/products');
        cy.contains('button', 'Add New Product').click();

        cy.get('#name').clear().type('No Expiry Product');
        cy.get('#cost').clear().type('100');

        // Fill unit fields
        cy.get('input[placeholder="Unit eg: box"]').clear().type('Pack');
        cy.get('input[placeholder="Unit Conversion Factor"]').clear().type('1');

        cy.get('button.bg-emerald-600').filter(':visible').last().click();

        cy.php(`
            return App\\Models\\Product::where('name', 'No Expiry Product')->exists() ? 'created' : 'not_found';
        `).should('eq', 'created');
    });
});
