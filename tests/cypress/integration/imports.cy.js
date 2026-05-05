/**
 * E2E tests: Import flow (customers & products, default & QuickBooks templates)
 */

before(() => {
    Cypress.session.clearAllSavedSessions();
    cy.refreshDatabase();
});

beforeEach(() => {
    cy.tenantLogin('cypress');
});

/*
|--------------------------------------------------------------------------
| Customer Import - Default Template
|--------------------------------------------------------------------------
*/
describe('Customer Import (Default Template)', () => {
    it('shows import button and opens modal', () => {
        cy.visit('/customers');
        cy.contains('button', 'Import').should('exist').click();
        cy.contains('Import Data').should('be.visible');
        cy.contains('System Template').should('be.visible');
        cy.contains('QuickBooks').should('be.visible');
        cy.contains('Download sample file').should('be.visible');
    });

    it('disables submit when no file is selected', () => {
        cy.visit('/customers');
        cy.contains('button', 'Import').click();
        cy.get('.bg-emerald-600').filter(':visible').last().should('be.disabled');
    });

    it('imports customers from CSV and creates records', () => {
        cy.visit('/customers');
        cy.contains('button', 'Import').click();

        cy.get('input[type="file"]').selectFile('tests/cypress/fixtures/customers-default.csv', { force: true });
        cy.get('.bg-emerald-600').filter(':visible').last().click();

        cy.url().should('include', '/customers');
        cy.visit('/customers');
        cy.contains('Cypress Customer A').should('exist');
        cy.contains('Cypress Customer B').should('exist');
    });

    it('skips invalid rows but imports valid ones', () => {
        cy.visit('/customers');
        cy.contains('button', 'Import').click();

        cy.get('input[type="file"]').selectFile('tests/cypress/fixtures/customers-invalid.csv', { force: true });
        cy.get('.bg-emerald-600').filter(':visible').last().click();

        cy.visit('/customers');
        cy.contains('Valid Row').should('exist');
    });

    it('stores default template on import log', () => {
        cy.php(`
            $log = App\\Models\\ImportLog::where('import_type', 'customers')
                ->where('template', 'default')
                ->latest()
                ->first();
            return $log ? $log->status->value : 'not_found';
        `).should('eq', 'completed');
    });
});

/*
|--------------------------------------------------------------------------
| Customer Import - QuickBooks Template
|--------------------------------------------------------------------------
*/
describe('Customer Import (QuickBooks Template)', () => {
    // Run the import once for all verification tests
    before(() => {
        cy.tenantLogin('cypress');
        cy.visit('/customers');
        cy.contains('button', 'Import').click();
        cy.contains('button', 'QuickBooks').click();
        cy.get('input[type="file"]').selectFile('tests/cypress/fixtures/quickbooks-customers.xlsx', { force: true });
        cy.get('.bg-emerald-600').filter(':visible').last().click();
        cy.url().should('include', '/customers');
    });

    it('imports active customers from QuickBooks XLSX', () => {
        cy.visit('/customers');
        cy.contains('QB Cypress Customer Alpha').should('exist');
        cy.contains('QB Cypress Customer Beta').should('exist');
        cy.contains('QB Cypress Customer Gamma').should('exist');
    });

    it('skips inactive customers', () => {
        cy.php(`
            return App\\Models\\Customer::where('name', 'QB Inactive Customer')->exists() ? 'exists' : 'skipped';
        `).should('eq', 'skipped');
    });

    it('skips dot-only and empty customer names', () => {
        cy.php(`
            $junk = App\\Models\\Customer::whereIn('name', ['.', '..................', ''])->count();
            return $junk === 0 ? 'clean' : 'has_junk';
        `).should('eq', 'clean');
    });

    it('maps positive balance to opening_debit', () => {
        cy.php(`
            $c = App\\Models\\Customer::where('name', 'QB Cypress Customer Alpha')->first();
            return $c ? ['debit' => (float) $c->opening_debit, 'credit' => (float) $c->opening_credit] : null;
        `).then((r) => {
            expect(r.debit).to.eq(2500);
            expect(r.credit).to.eq(0);
        });
    });

    it('maps negative balance to opening_credit', () => {
        cy.php(`
            $c = App\\Models\\Customer::where('name', 'QB Cypress Customer Beta')->first();
            return $c ? ['debit' => (float) $c->opening_debit, 'credit' => (float) $c->opening_credit] : null;
        `).then((r) => {
            expect(r.debit).to.eq(0);
            expect(r.credit).to.eq(800);
        });
    });

    it('maps phone, address, and credit limit', () => {
        cy.php(`
            $c = App\\Models\\Customer::where('name', 'QB Cypress Customer Alpha')->first();
            return $c ? ['phone' => $c->phone_number, 'address' => $c->address, 'limit' => (float) $c->credit_limit] : null;
        `).then((r) => {
            expect(r.phone).to.eq('0551111111');
            expect(r.address).to.eq('Khartoum North');
            expect(r.limit).to.eq(10000);
        });
    });

    it('stores quickbooks template on import log with correct row count', () => {
        cy.php(`
            $log = App\\Models\\ImportLog::where('import_type', 'customers')
                ->where('template', 'quickbooks')
                ->latest()
                ->first();
            return $log ? ['status' => $log->status->value, 'rows' => $log->rows_imported] : null;
        `).then((r) => {
            expect(r.status).to.eq('completed');
            expect(r.rows).to.eq(3);
        });
    });
});

/*
|--------------------------------------------------------------------------
| Product Import - Default Template
|--------------------------------------------------------------------------
*/
describe('Product Import (Default Template)', () => {
    it('shows import button and opens modal with templates', () => {
        cy.visit('/products');
        cy.contains('button', 'Import').should('exist').click();
        cy.contains('Import Data').should('be.visible');
        cy.contains('System Template').should('be.visible');
        cy.contains('QuickBooks').should('be.visible');
    });

    it('imports products from CSV with price, categories, and units', () => {
        cy.visit('/products');
        cy.contains('button', 'Import').click();

        cy.get('input[type="file"]').selectFile('tests/cypress/fixtures/products-default.csv', { force: true });
        cy.get('.bg-emerald-600').filter(':visible').last().click();

        cy.url().should('include', '/products');
        cy.visit('/products');
        cy.contains('Cypress Product A').should('exist');
        cy.contains('Cypress Product B').should('exist');
    });

    it('verifies imported product cost, price, categories, and units', () => {
        cy.php(`
            $p = App\\Models\\Product::where('name', 'Cypress Product A')->with('categories', 'units')->first();
            return $p ? [
                'cost' => $p->cost,
                'price' => $p->price,
                'categories' => $p->categories->pluck('name')->toArray(),
                'unit_name' => $p->units->first()?->name,
                'unit_factor' => (int) $p->units->first()?->conversion_factor,
            ] : null;
        `).then((r) => {
            expect(r).to.not.be.null;
            expect(r.cost).to.eq(100);
            expect(r.price).to.eq(120);
            expect(r.categories).to.include('Drinks');
            expect(r.categories).to.include('Juice');
            expect(r.unit_name).to.eq('Box');
            expect(r.unit_factor).to.eq(10);
        });
    });
});

/*
|--------------------------------------------------------------------------
| Product Import - QuickBooks Template
|--------------------------------------------------------------------------
*/
describe('Product Import (QuickBooks Template)', () => {
    // Run the QB import once for all verification tests
    before(() => {
        cy.tenantLogin('cypress');
        cy.visit('/products');
        cy.contains('button', 'Import').click();
        cy.contains('button', 'QuickBooks').click();
        cy.get('input[type="file"]').selectFile('tests/cypress/fixtures/quickbooks-products.xlsx', { force: true });
        cy.get('.bg-emerald-600').filter(':visible').last().click();
        cy.url().should('include', '/products');
    });

    it('imports Inventory Part products', () => {
        cy.visit('/products');
        cy.contains('QB Cypress Medicine A').should('exist');
        cy.contains('QB Cypress Medicine B').should('exist');
        cy.contains('QB Cypress Standalone Product').should('exist');
        cy.contains('QB Cypress Supply Item').should('exist');
        cy.contains('QB Cypress Medicine C').should('exist');
    });

    it('skips Service, Subtotal, and Inactive items', () => {
        cy.php(`
            $service = App\\Models\\Product::where('name', 'QB Service Item')->exists();
            $subtotal = App\\Models\\Product::where('name', 'Subtotal Line')->exists();
            $inactive = App\\Models\\Product::where('name', 'QB Inactive Product')->exists();
            return (!$service && !$subtotal && !$inactive) ? 'clean' : 'has_excluded';
        `).should('eq', 'clean');
    });

    it('skips dot-only and empty product names', () => {
        cy.php(`
            return App\\Models\\Product::where('name', '.')->exists() ? 'junk' : 'clean';
        `).should('eq', 'clean');
    });

    it('extracts name from colon notation and creates category from prefix', () => {
        cy.php(`
            $p = App\\Models\\Product::where('name', 'QB Cypress Medicine A')->with('categories')->first();
            return $p ? [
                'name' => $p->name,
                'categories' => $p->categories->pluck('name')->toArray(),
            ] : null;
        `).then((r) => {
            expect(r.name).to.eq('QB Cypress Medicine A');
            expect(r.categories).to.include('ادوية بيطرية');
        });
    });

    it('creates separate categories for different colon prefixes', () => {
        cy.php(`
            $p = App\\Models\\Product::where('name', 'QB Cypress Supply Item')->with('categories')->first();
            return $p ? $p->categories->pluck('name')->toArray() : [];
        `).then((cats) => {
            expect(cats).to.include('مستلزمات');
        });
    });

    it('top-level products have no category', () => {
        cy.php(`
            $p = App\\Models\\Product::where('name', 'QB Cypress Standalone Product')->first();
            return $p ? $p->categories->count() : -1;
        `).should('eq', 0);
    });

    it('maps cost and price correctly, falls back price to cost when zero', () => {
        cy.php(`
            $a = App\\Models\\Product::where('name', 'QB Cypress Medicine A')->first();
            $b = App\\Models\\Product::where('name', 'QB Cypress Standalone Product')->first();
            return [
                'a_cost' => $a->cost, 'a_price' => $a->price,
                'b_cost' => $b->cost, 'b_price' => $b->price,
            ];
        `).then((r) => {
            expect(r.a_cost).to.eq(150);
            expect(r.a_price).to.eq(250);
            expect(r.b_cost).to.eq(500);
            expect(r.b_price).to.eq(500); // price was 0, falls back to cost
        });
    });

    it('parses unit name from QB format and skips when absent', () => {
        cy.php(`
            $withUnit = App\\Models\\Product::where('name', 'QB Cypress Medicine A')->first();
            $noUnit = App\\Models\\Product::where('name', 'QB Cypress Medicine B')->first();
            return [
                'unit' => $withUnit->units->first()?->name,
                'no_unit_count' => $noUnit->units->count(),
            ];
        `).then((r) => {
            expect(r.unit).to.eq('each');
            expect(r.no_unit_count).to.eq(0);
        });
    });

    it('stores quickbooks template on import log with correct row count', () => {
        cy.php(`
            $log = App\\Models\\ImportLog::where('import_type', 'products')
                ->where('template', 'quickbooks')
                ->latest()
                ->first();
            return $log ? ['status' => $log->status->value, 'rows' => $log->rows_imported] : null;
        `).then((r) => {
            expect(r.status).to.eq('completed');
            expect(r.rows).to.eq(5);
        });
    });
});

/*
|--------------------------------------------------------------------------
| Supplier Import
|--------------------------------------------------------------------------
*/
describe('Supplier Import', () => {
    it('shows import button and modal with Coming Soon QuickBooks badge', () => {
        cy.visit('/suppliers');
        cy.contains('button', 'Import').should('exist').click();
        cy.contains('Import Data').should('be.visible');
        cy.contains('Coming Soon').should('be.visible');
    });

    it('QuickBooks template button is disabled', () => {
        cy.visit('/suppliers');
        cy.contains('button', 'Import').click();
        cy.contains('button', 'QuickBooks').should('be.disabled');
    });
});

/*
|--------------------------------------------------------------------------
| Import Validation
|--------------------------------------------------------------------------
*/
describe('Import Validation', () => {
    it('rejects request without a file', () => {
        cy.csrfToken().then((token) => {
            cy.request({
                method: 'POST',
                url: '/customers/import',
                form: true,
                body: { _token: token, template: 'default' },
                failOnStatusCode: false,
                followRedirect: false,
            }).then((res) => {
                expect(res.status).to.eq(302);
            });
        });
    });

    it('rejects invalid template value', () => {
        cy.csrfToken().then((token) => {
            cy.request({
                method: 'POST',
                url: '/customers/import',
                form: true,
                body: { _token: token, template: 'nonexistent' },
                failOnStatusCode: false,
                followRedirect: false,
            }).then((res) => {
                expect(res.status).to.eq(302);
            });
        });
    });
});
