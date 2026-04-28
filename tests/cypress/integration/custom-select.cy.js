/**
 * E2E tests: CustomSelect dropdown component
 *
 * Tests the shared dropdown component across different contexts:
 * - Standard page forms (payments, sales, purchases)
 * - Inside modals (customer form, product form)
 * - Search, selection, tagging, keyboard interaction
 * - Visibility and positioning (not clipped by containers)
 */

before(() => {
    cy.refreshDatabase();
    cy.tenantLogin();

    cy.php(`
        $tenant = App\\Models\\Tenant::where('slug', 'cypress-test')->first();
        app()->instance('currentTenant', $tenant);

        App\\Models\\Customer::factory()->count(3)->sequence(
            ['name' => 'Alpha Customer'],
            ['name' => 'Beta Customer'],
            ['name' => 'Gamma Customer']
        )->create();

        App\\Models\\Supplier::factory()->create(['name' => 'Test Supplier']);

        App\\Models\\Product::factory()
            ->has(App\\Models\\Unit::factory()->state(['name' => 'piece', 'conversion_factor' => 1]), 'units')
            ->create(['name' => 'Select Test Product']);

        return ['ok' => true]
    `);
});

beforeEach(() => {
    cy.tenantLogin();
});

describe('CustomSelect - Dropdown Visibility', () => {
    it('dropdown is visible when opened on a standard page', () => {
        cy.visit('/payments/create');

        // Open the first dropdown (Payment For)
        cy.get('.custom-select').first().find('.custom-select__trigger').click();

        // Dropdown should be visible in the body
        cy.get('.custom-select__dropdown').should('be.visible');
        cy.get('.custom-select__dropdown .custom-select__options').should('be.visible');
    });

    it('dropdown is not clipped by parent containers', () => {
        cy.visit('/payments/create');

        cy.get('.custom-select').first().find('.custom-select__trigger').click();
        cy.get('.custom-select__dropdown').should('be.visible').then(($dropdown) => {
            const rect = $dropdown[0].getBoundingClientRect();

            // Dropdown should be within the viewport
            expect(rect.top).to.be.greaterThan(0);
            expect(rect.left).to.be.greaterThan(0);
            expect(rect.bottom).to.be.lessThan(Cypress.config('viewportHeight') + 10);
            expect(rect.width).to.be.greaterThan(50);
        });
    });

    it('dropdown appears above surrounding UI elements (z-index)', () => {
        cy.visit('/payments/create');

        cy.get('.custom-select').first().find('.custom-select__trigger').click();
        cy.get('.custom-select__dropdown').should('be.visible').then(($dropdown) => {
            const zIndex = parseInt(window.getComputedStyle($dropdown[0]).zIndex);
            expect(zIndex).to.be.greaterThan(0);
        });
    });

    it('dropdown closes when clicking outside', () => {
        cy.visit('/payments/create');

        cy.get('.custom-select').first().find('.custom-select__trigger').click();
        cy.get('.custom-select__dropdown').should('be.visible');

        // Click outside the dropdown
        cy.get('body').click(10, 10);
        cy.get('.custom-select__dropdown').should('not.exist');
    });
});

describe('CustomSelect - Search', () => {
    it('filters options as user types', () => {
        cy.visit('/sales/create');

        // Open customer selector
        cy.get('.custom-select').first().find('.custom-select__trigger').click();
        cy.get('.custom-select__dropdown').should('be.visible');

        // Type a search query
        cy.get('.custom-select__dropdown .custom-select__search-input').type('Alpha');

        // Only matching options should appear
        cy.get('.custom-select__dropdown .custom-select__option').should('have.length', 1);
        cy.get('.custom-select__dropdown .custom-select__option').first().should('contain', 'Alpha');
    });

    it('shows empty state when no options match', () => {
        cy.visit('/sales/create');

        cy.get('.custom-select').first().find('.custom-select__trigger').click();
        cy.get('.custom-select__dropdown .custom-select__search-input').type('NonexistentXYZ123');

        cy.get('.custom-select__dropdown .custom-select__no-results').should('be.visible');
    });
});

describe('CustomSelect - Selection', () => {
    it('selects an option and closes the dropdown', () => {
        cy.visit('/sales/create');

        cy.get('.custom-select').first().find('.custom-select__trigger').click();
        cy.get('.custom-select__dropdown .custom-select__option').first().click();

        // Dropdown should close
        cy.get('.custom-select__dropdown').should('not.exist');

        // The trigger should display the selected value
        cy.get('.custom-select').first().find('.custom-select__selected').should('exist');
    });

    it('correctly aligns dropdown with the trigger input', () => {
        cy.visit('/payments/create');

        cy.get('.custom-select').first().then(($trigger) => {
            const triggerRect = $trigger[0].getBoundingClientRect();

            $trigger.find('.custom-select__trigger').trigger('click');

            cy.get('.custom-select__dropdown').should('be.visible').then(($dropdown) => {
                const dropdownRect = $dropdown[0].getBoundingClientRect();

                // Width should match the trigger
                expect(Math.abs(dropdownRect.width - triggerRect.width)).to.be.lessThan(2);
                // Left edge should align
                expect(Math.abs(dropdownRect.left - triggerRect.left)).to.be.lessThan(2);
            });
        });
    });
});

describe('CustomSelect - Inside Modal (constrained container)', () => {
    it('dropdown is visible when opened inside a customer modal', () => {
        cy.visit('/customers');

        // Open the customer creation modal
        cy.contains('button', 'Add New').click();
        cy.contains('Add New').should('be.visible');

        // The categories CustomSelect is the only one in the modal — target it specifically
        cy.get('.custom-select').first().find('.custom-select__trigger').click();

        // Dropdown should be visible and not clipped by modal overflow
        cy.get('.custom-select__dropdown').should('be.visible').then(($dropdown) => {
            const rect = $dropdown[0].getBoundingClientRect();
            expect(rect.width).to.be.greaterThan(50);
            expect(rect.height).to.be.greaterThan(10);
        });
    });

    it('dropdown is visible inside product creation modal', () => {
        cy.visit('/products');

        cy.contains('button', 'Add New Product').click();
        cy.contains('Add New Product').should('be.visible');

        // Open the categories CustomSelect inside the product modal
        cy.get('.custom-select').first().find('.custom-select__trigger').click();

        cy.get('.custom-select__dropdown').should('be.visible').then(($dropdown) => {
            const rect = $dropdown[0].getBoundingClientRect();
            expect(rect.width).to.be.greaterThan(50);
            expect(rect.height).to.be.greaterThan(10);
        });
    });
});

describe('CustomSelect - Tagging', () => {
    it('shows tag creation option for new entries', () => {
        cy.visit('/customers');

        cy.contains('button', 'Add New').click();
        cy.contains('Add New').should('be.visible');

        // Categories field is taggable — target the first custom-select in the modal
        cy.get('.custom-select').first().find('.custom-select__trigger').click();
        cy.get('.custom-select__dropdown .custom-select__search-input').type('NewCategory');

        // Should show a tag creation option
        cy.get('.custom-select__dropdown .custom-select__option--tag').should('be.visible');
    });
});
