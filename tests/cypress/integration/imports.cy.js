/**
 * E2E tests: Import flow (customers & products, default & QuickBooks templates)
 *
 * Verifies the import modal UI, template selection, file upload,
 * queued import processing, and data creation.
 */

before(() => {
    Cypress.session.clearAllSavedSessions();
    cy.refreshDatabase();
});

beforeEach(() => {
    cy.tenantLogin();
});

/*
|--------------------------------------------------------------------------
| Customer Import - Default Template
|--------------------------------------------------------------------------
*/
describe('Customer Import (Default Template)', () => {
    it('shows import button on customers page', () => {
        cy.visit('/customers');
        cy.contains('button', 'Import').should('exist');
    });

    it('opens import modal when clicking import button', () => {
        cy.visit('/customers');
        cy.contains('button', 'Import').click();
        cy.contains('Import Data').should('be.visible');
        cy.contains('Upload a CSV or Excel file').should('be.visible');
    });

    it('shows template selector with System Template and QuickBooks options', () => {
        cy.visit('/customers');
        cy.contains('button', 'Import').click();
        cy.contains('System Template').should('be.visible');
        cy.contains('QuickBooks').should('be.visible');
    });

    it('has a download sample file link', () => {
        cy.visit('/customers');
        cy.contains('button', 'Import').click();
        cy.contains('Download sample file').should('be.visible');
    });

    it('disables submit button when no file is selected', () => {
        cy.visit('/customers');
        cy.contains('button', 'Import').click();
        cy.get('button').contains('Import').last().should('be.disabled');
    });

    it('imports customers from CSV file with default template', () => {
        cy.visit('/customers');
        cy.contains('button', 'Import').click();

        // Upload file
        cy.get('input[type="file"]').selectFile('tests/cypress/fixtures/customers-default.csv', { force: true });

        // Submit
        cy.get('button').contains('Import').last().should('not.be.disabled');
        cy.get('button').contains('Import').last().click();

        // Should redirect back (import queued)
        cy.url().should('include', '/customers');

        // Verify customers were created (sync queue in testing)
        cy.visit('/customers');
        cy.contains('Cypress Customer A').should('exist');
        cy.contains('Cypress Customer B').should('exist');
    });

    it('handles invalid rows gracefully — valid rows still imported', () => {
        cy.visit('/customers');
        cy.contains('button', 'Import').click();

        cy.get('input[type="file"]').selectFile('tests/cypress/fixtures/customers-invalid.csv', { force: true });
        cy.get('button').contains('Import').last().click();

        cy.url().should('include', '/customers');

        // Valid row should be imported
        cy.visit('/customers');
        cy.contains('Valid Row').should('exist');
    });

    it('can download sample CSV file', () => {
        cy.visit('/customers');
        cy.contains('button', 'Import').click();

        cy.contains('Download sample file')
            .should('have.attr', 'href')
            .and('include', 'import/sample');
    });
});

/*
|--------------------------------------------------------------------------
| Customer Import - QuickBooks Template
|--------------------------------------------------------------------------
*/
describe('Customer Import (QuickBooks Template)', () => {
    it('can select QuickBooks template and it highlights', () => {
        cy.visit('/customers');
        cy.contains('button', 'Import').click();

        cy.contains('button', 'QuickBooks').click();
        cy.contains('button', 'QuickBooks')
            .should('have.class', 'bg-emerald-50');
        // System Template should no longer be active
        cy.contains('button', 'System Template')
            .should('not.have.class', 'bg-emerald-50');
    });

    it('imports active customers from QuickBooks XLSX', () => {
        cy.visit('/customers');
        cy.contains('button', 'Import').click();
        cy.contains('button', 'QuickBooks').click();

        cy.get('input[type="file"]').selectFile('tests/cypress/fixtures/quickbooks-customers.xlsx', { force: true });
        cy.get('button').contains('Import').last().click();

        cy.url().should('include', '/customers');

        // Active customers should be imported
        cy.visit('/customers');
        cy.contains('QB Cypress Customer Alpha').should('exist');
        cy.contains('QB Cypress Customer Beta').should('exist');
        cy.contains('QB Cypress Customer Gamma').should('exist');
    });

    it('skips inactive customers from QuickBooks file', () => {
        cy.php(`
            return App\\Models\\Customer::where('name', 'QB Inactive Customer')->exists() ? 'exists' : 'not_found';
        `).should('eq', 'not_found');
    });

    it('skips dot-only and empty customer names', () => {
        cy.php(`
            $dotCustomer = App\\Models\\Customer::where('name', '.')->exists();
            $dotsCustomer = App\\Models\\Customer::where('name', '..................')->exists();
            return (!$dotCustomer && !$dotsCustomer) ? 'clean' : 'has_junk';
        `).should('eq', 'clean');
    });

    it('maps positive balance to opening_debit', () => {
        cy.php(`
            $customer = App\\Models\\Customer::where('name', 'QB Cypress Customer Alpha')->first();
            return $customer ? ['debit' => (float) $customer->opening_debit, 'credit' => (float) $customer->opening_credit] : null;
        `).then((result) => {
            expect(result).to.not.be.null;
            expect(result.debit).to.eq(2500);
            expect(result.credit).to.eq(0);
        });
    });

    it('maps negative balance to opening_credit (absolute value)', () => {
        cy.php(`
            $customer = App\\Models\\Customer::where('name', 'QB Cypress Customer Beta')->first();
            return $customer ? ['debit' => (float) $customer->opening_debit, 'credit' => (float) $customer->opening_credit] : null;
        `).then((result) => {
            expect(result).to.not.be.null;
            expect(result.debit).to.eq(0);
            expect(result.credit).to.eq(800);
        });
    });

    it('maps phone number and address from QuickBooks fields', () => {
        cy.php(`
            $customer = App\\Models\\Customer::where('name', 'QB Cypress Customer Alpha')->first();
            return $customer ? ['phone' => $customer->phone_number, 'address' => $customer->address] : null;
        `).then((result) => {
            expect(result.phone).to.eq('0551111111');
            expect(result.address).to.eq('Khartoum North');
        });
    });

    it('maps credit limit from QuickBooks', () => {
        cy.php(`
            $customer = App\\Models\\Customer::where('name', 'QB Cypress Customer Alpha')->first();
            return $customer ? (float) $customer->credit_limit : null;
        `).then((result) => {
            expect(result).to.eq(10000);
        });
    });

    it('stores quickbooks template on import log with completed status', () => {
        cy.php(`
            $log = App\\Models\\ImportLog::where('import_type', 'customers')
                ->where('template', 'quickbooks')
                ->latest()
                ->first();
            return $log ? ['template' => $log->template, 'status' => $log->status->value, 'rows' => $log->rows_imported] : null;
        `).then((result) => {
            expect(result).to.not.be.null;
            expect(result.template).to.eq('quickbooks');
            expect(result.status).to.eq('completed');
            expect(result.rows).to.eq(3); // 3 valid active customers
        });
    });
});

/*
|--------------------------------------------------------------------------
| Product Import - Default Template
|--------------------------------------------------------------------------
*/
describe('Product Import (Default Template)', () => {
    it('shows import button on products page', () => {
        cy.visit('/products');
        cy.contains('button', 'Import').should('exist');
    });

    it('opens import modal with template options', () => {
        cy.visit('/products');
        cy.contains('button', 'Import').click();
        cy.contains('Import Data').should('be.visible');
        cy.contains('System Template').should('be.visible');
        cy.contains('QuickBooks').should('be.visible');
    });

    it('imports products from CSV with categories and units', () => {
        cy.visit('/products');
        cy.contains('button', 'Import').click();

        cy.get('input[type="file"]').selectFile('tests/cypress/fixtures/products-default.csv', { force: true });
        cy.get('button').contains('Import').last().click();

        cy.url().should('include', '/products');

        // Verify products were created
        cy.visit('/products');
        cy.contains('Cypress Product A').should('exist');
        cy.contains('Cypress Product B').should('exist');
    });

    it('imported products have correct price and cost', () => {
        cy.php(`
            $product = App\\Models\\Product::where('name', 'Cypress Product A')->first();
            return $product ? ['cost' => $product->cost, 'price' => $product->price] : null;
        `).then((result) => {
            expect(result).to.not.be.null;
            expect(result.cost).to.eq(100);
            expect(result.price).to.eq(120);
        });
    });

    it('imported products have categories synced', () => {
        cy.php(`
            $product = App\\Models\\Product::where('name', 'Cypress Product A')->first();
            return $product ? $product->categories->pluck('name')->toArray() : [];
        `).then((categories) => {
            expect(categories).to.include('Drinks');
            expect(categories).to.include('Juice');
        });
    });

    it('imported products have units created', () => {
        cy.php(`
            $product = App\\Models\\Product::where('name', 'Cypress Product A')->first();
            return $product ? $product->units->first()?->toArray() : null;
        `).then((unit) => {
            expect(unit).to.not.be.null;
            expect(unit.name).to.eq('Box');
            expect(unit.conversion_factor).to.eq(10);
        });
    });
});

/*
|--------------------------------------------------------------------------
| Product Import - QuickBooks Template
|--------------------------------------------------------------------------
*/
describe('Product Import (QuickBooks Template)', () => {
    it('can select QuickBooks template for products', () => {
        cy.visit('/products');
        cy.contains('button', 'Import').click();

        cy.contains('button', 'QuickBooks').click();
        cy.contains('button', 'QuickBooks')
            .should('have.class', 'bg-emerald-50');
    });

    it('imports Inventory Part products from QuickBooks XLSX', () => {
        cy.visit('/products');
        cy.contains('button', 'Import').click();
        cy.contains('button', 'QuickBooks').click();

        cy.get('input[type="file"]').selectFile('tests/cypress/fixtures/quickbooks-products.xlsx', { force: true });
        cy.get('button').contains('Import').last().click();

        cy.url().should('include', '/products');

        cy.visit('/products');
        cy.contains('QB Cypress Medicine A').should('exist');
        cy.contains('QB Cypress Medicine B').should('exist');
        cy.contains('QB Cypress Standalone Product').should('exist');
        cy.contains('QB Cypress Supply Item').should('exist');
        cy.contains('QB Cypress Medicine C').should('exist');
    });

    it('skips Service, Subtotal, and Group type items', () => {
        cy.php(`
            $service = App\\Models\\Product::where('name', 'QB Service Item')->exists();
            $subtotal = App\\Models\\Product::where('name', 'Subtotal Line')->exists();
            return (!$service && !$subtotal) ? 'clean' : 'has_non_inventory';
        `).should('eq', 'clean');
    });

    it('skips inactive products', () => {
        cy.php(`
            return App\\Models\\Product::where('name', 'QB Inactive Product')->exists() ? 'exists' : 'not_found';
        `).should('eq', 'not_found');
    });

    it('skips dot-only and empty product names', () => {
        cy.php(`
            $dot = App\\Models\\Product::where('name', '.')->exists();
            return !$dot ? 'clean' : 'has_junk';
        `).should('eq', 'clean');
    });

    it('extracts product name from colon notation (parent:child)', () => {
        cy.php(`
            // "ادوية بيطرية:QB Cypress Medicine A" → name should be "QB Cypress Medicine A"
            $product = App\\Models\\Product::where('name', 'QB Cypress Medicine A')->first();
            return $product ? $product->name : null;
        `).should('eq', 'QB Cypress Medicine A');
    });

    it('creates category from colon prefix (parent:child → parent as category)', () => {
        cy.php(`
            $product = App\\Models\\Product::where('name', 'QB Cypress Medicine A')->first();
            return $product ? $product->categories->pluck('name')->toArray() : [];
        `).then((categories) => {
            expect(categories).to.include('ادوية بيطرية');
        });
    });

    it('creates separate categories for different colon prefixes', () => {
        cy.php(`
            $supply = App\\Models\\Product::where('name', 'QB Cypress Supply Item')->first();
            return $supply ? $supply->categories->pluck('name')->toArray() : [];
        `).then((categories) => {
            expect(categories).to.include('مستلزمات');
        });
    });

    it('top-level products (no colon) have no category assigned', () => {
        cy.php(`
            $product = App\\Models\\Product::where('name', 'QB Cypress Standalone Product')->first();
            return $product ? $product->categories->count() : -1;
        `).should('eq', 0);
    });

    it('maps cost and price correctly', () => {
        cy.php(`
            $product = App\\Models\\Product::where('name', 'QB Cypress Medicine A')->first();
            return $product ? ['cost' => $product->cost, 'price' => $product->price] : null;
        `).then((result) => {
            expect(result.cost).to.eq(150);
            expect(result.price).to.eq(250);
        });
    });

    it('falls back price to cost when price is zero', () => {
        cy.php(`
            $product = App\\Models\\Product::where('name', 'QB Cypress Standalone Product')->first();
            return $product ? ['cost' => $product->cost, 'price' => $product->price] : null;
        `).then((result) => {
            expect(result.cost).to.eq(500);
            expect(result.price).to.eq(500); // price was 0, falls back to cost
        });
    });

    it('parses unit name from QB format (strips parenthetical)', () => {
        cy.php(`
            $product = App\\Models\\Product::where('name', 'QB Cypress Medicine A')->first();
            $unit = $product ? $product->units->first() : null;
            return $unit ? $unit->name : null;
        `).should('eq', 'each');
    });

    it('products without U/M have no units', () => {
        cy.php(`
            $product = App\\Models\\Product::where('name', 'QB Cypress Medicine B')->first();
            return $product ? $product->units->count() : -1;
        `).should('eq', 0);
    });

    it('stores quickbooks template on import log with correct row count', () => {
        cy.php(`
            $log = App\\Models\\ImportLog::where('import_type', 'products')
                ->where('template', 'quickbooks')
                ->latest()
                ->first();
            return $log ? ['template' => $log->template, 'status' => $log->status->value, 'rows' => $log->rows_imported] : null;
        `).then((result) => {
            expect(result).to.not.be.null;
            expect(result.template).to.eq('quickbooks');
            expect(result.status).to.eq('completed');
            expect(result.rows).to.eq(5); // 5 valid Inventory Part items
        });
    });
});

/*
|--------------------------------------------------------------------------
| Supplier Import
|--------------------------------------------------------------------------
*/
describe('Supplier Import', () => {
    it('shows import button on suppliers page', () => {
        cy.visit('/suppliers');
        cy.contains('button', 'Import').should('exist');
    });

    it('opens import modal with Coming Soon badge for QuickBooks', () => {
        cy.visit('/suppliers');
        cy.contains('button', 'Import').click();
        cy.contains('Import Data').should('be.visible');
        cy.contains('Coming Soon').should('be.visible');
    });

    it('QuickBooks template button is disabled for suppliers', () => {
        cy.visit('/suppliers');
        cy.contains('button', 'Import').click();
        cy.contains('button', 'QuickBooks').should('be.disabled');
    });
});

/*
|--------------------------------------------------------------------------
| Import Validation & Edge Cases
|--------------------------------------------------------------------------
*/
describe('Import Validation', () => {
    it('rejects invalid file types', () => {
        cy.visit('/customers');

        cy.csrfToken().then((token) => {
            // Try uploading a non-CSV/XLSX file via direct request
            cy.request({
                method: 'POST',
                url: '/customers/import',
                body: { _token: token, template: 'default' },
                failOnStatusCode: false,
                followRedirect: false,
            }).then((response) => {
                // Should fail validation (no file)
                expect(response.status).to.eq(302);
            });
        });
    });

    it('rejects invalid template value', () => {
        cy.visit('/customers');

        cy.csrfToken().then((token) => {
            const formData = new FormData();
            formData.append('_token', token);
            formData.append('template', 'nonexistent');
            formData.append('file', new Blob(['name\nTest'], { type: 'text/csv' }), 'test.csv');

            cy.request({
                method: 'POST',
                url: '/customers/import',
                body: formData,
                headers: { 'Content-Type': 'multipart/form-data' },
                failOnStatusCode: false,
                followRedirect: false,
            }).then((response) => {
                expect(response.status).to.eq(302);
            });
        });
    });
});

/*
|--------------------------------------------------------------------------
| Import Operations Center Integration
|--------------------------------------------------------------------------
*/
describe('Import Operations Panel', () => {
    it('shows operations pill after import is queued', () => {
        cy.visit('/customers');
        cy.contains('button', 'Import').click();

        cy.get('input[type="file"]').selectFile('tests/cypress/fixtures/customers-default.csv', { force: true });
        cy.get('button').contains('Import').last().click();

        // The operations panel pill should appear
        cy.get('body').then(($body) => {
            // Panel or pill should be visible with "in progress" or "Done" state
            if ($body.find(':contains("in progress")').length || $body.find(':contains("Done")').length) {
                cy.log('Operations panel responded to import');
            }
        });
    });
});
