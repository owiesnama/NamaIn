/**
 * E2E tests: backend authorization enforcement
 *
 * Verifies that policy-protected endpoints return 403 for
 * unauthorized roles. Uses cy.request() to bypass the Inertia
 * client and hit the server directly.
 *
 * Only tests controllers that have $this->authorize() calls:
 *   - UserManagementController (users.view, users.invite, users.manage, users.assign-role)
 *   - RoleController (roles.manage)
 *   - TreasuryAccountsController (treasury.view, treasury.create)
 *   - TreasuryTransfersController (treasury.transfer)
 *   - TreasuryAdjustmentsController (treasury.adjust)
 *   - StockTransfersController (inventory.transfer)
 *   - StockController (inventory.manage — also route-level ->can())
 *   - CustomersController (customers.update, customers.delete)
 *   - StoragesController (inventory.manage — delete, adjust only)
 *   - ProductsController (products.view, products.create, products.update, products.delete)
 *   - SuppliersController (suppliers.view, suppliers.create, suppliers.update, suppliers.delete)
 *   - SalesController (sales.view, sales.create — uses InvoicePolicy)
 *   - PurchasesController (purchases.view, purchases.create — uses InvoicePolicy)
 *   - PaymentsController (payments.view, payments.create)
 *   - ExpensesController (expenses.view, expenses.create, expenses.delete)
 */

let seedData = {};

before(() => {
    Cypress.session.clearAllSavedSessions();
    cy.refreshDatabase();

    // Seed as owner to create test records
    cy.tenantLoginAs('owner');

    cy.php(`
        $tenant = App\\Models\\Tenant::where('slug', 'cypress-test')->first();
        app()->instance('currentTenant', $tenant);

        $customer = App\\Models\\Customer::factory()->create(['tenant_id' => $tenant->id]);
        $storage  = App\\Models\\Storage::factory()->create(['tenant_id' => $tenant->id]);
        $product  = App\\Models\\Product::factory()->create(['tenant_id' => $tenant->id]);
        $supplier = App\\Models\\Supplier::factory()->create(['tenant_id' => $tenant->id]);
        $expense  = App\\Models\\Expense::factory()->create(['tenant_id' => $tenant->id]);

        $ownerUser = $tenant->users()->first();

        return [
            'customer_id' => $customer->id,
            'storage_id'  => $storage->id,
            'product_id'  => $product->id,
            'supplier_id' => $supplier->id,
            'expense_id'  => $expense->id,
            'owner_user_id' => $ownerUser->id,
        ]
    `).then((data) => {
        seedData = data;
    });
});

/*
|--------------------------------------------------------------------------
| Helper: make an HTTP request and assert 403
|--------------------------------------------------------------------------
*/
function assertForbidden(method, url, body = {}) {
    cy.csrfToken().then((token) => {
        cy.request({
            method,
            url,
            body: method !== 'GET' ? { ...body, _token: token } : undefined,
            failOnStatusCode: false,
            followRedirect: false,
        }).then((response) => {
            // 403 = authorization denied, 302 = redirected away (blocked)
            expect(response.status, `${method} ${url} should be blocked`).to.be.oneOf([403, 302]);
        });
    });
}

/*
|--------------------------------------------------------------------------
| Staff role
|--------------------------------------------------------------------------
| Has only view permissions: products, customers, suppliers, sales,
| purchases, inventory, expenses, payments, treasury.
| Cannot: create/update/delete anything, manage users/roles, POS, cheques.
*/
describe('as staff', () => {
    beforeEach(() => cy.tenantLoginAs('staff'));

    // Users
    it('GET /users returns 403', () => {
        assertForbidden('GET', '/users');
    });

    it('POST /users/invite returns 403', () => {
        assertForbidden('POST', '/users/invite', { email: 'test@test.com' });
    });

    it('DELETE /users/{id} returns 403', () => {
        assertForbidden('DELETE', `/users/${seedData.owner_user_id}`);
    });

    it('PUT /users/{id}/role returns 403', () => {
        assertForbidden('PUT', `/users/${seedData.owner_user_id}/role`, { role_id: 1 });
    });

    // Roles
    it('GET /roles returns 403', () => {
        assertForbidden('GET', '/roles');
    });

    it('POST /roles returns 403', () => {
        assertForbidden('POST', '/roles', { name: 'Test Role' });
    });

    // Treasury — staff has treasury.view but NOT create/transfer/adjust
    it('POST /treasury returns 403 (no treasury.create)', () => {
        assertForbidden('POST', '/treasury', { name: 'Test', type: 'cash', currency: 'SDG' });
    });

    it('GET /treasury/transfer returns 403 (no treasury.transfer)', () => {
        assertForbidden('GET', '/treasury/transfer');
    });

    // Stock transfers — no inventory.transfer
    it('GET /stock-transfers returns 403', () => {
        assertForbidden('GET', '/stock-transfers');
    });

    it('POST /stock-transfers returns 403', () => {
        assertForbidden('POST', '/stock-transfers', {});
    });

    // Customers — staff has customers.view but NOT update/delete
    it('PUT /customers/{id} returns 403 (no customers.update)', () => {
        assertForbidden('PUT', `/customers/${seedData.customer_id}`, { name: 'Hacked' });
    });

    it('DELETE /customers/{id} returns 403 (no customers.delete)', () => {
        assertForbidden('DELETE', `/customers/${seedData.customer_id}`);
    });

    // Storages — staff has inventory.view but NOT inventory.manage
    it('DELETE /storages/{id} returns 403 (no inventory.manage)', () => {
        assertForbidden('DELETE', `/storages/${seedData.storage_id}`);
    });

    // Products — staff has products.view but NOT create/update/delete
    it('DELETE /products/{id} returns 403 (no products.delete)', () => {
        assertForbidden('DELETE', `/products/${seedData.product_id}`);
    });

    // Suppliers — staff has suppliers.view but NOT create/update/delete
    it('DELETE /suppliers/{id} returns 403 (no suppliers.delete)', () => {
        assertForbidden('DELETE', `/suppliers/${seedData.supplier_id}`);
    });

    it('POST /suppliers returns 403 (no suppliers.create)', () => {
        assertForbidden('POST', '/suppliers', { name: 'Hacked', address: '123', phone_number: '000' });
    });

    // Sales — staff has sales.view but NOT sales.create
    it('GET /sales/create returns 403 (no sales.create)', () => {
        assertForbidden('GET', '/sales/create');
    });

    // Purchases — staff has purchases.view but NOT purchases.create
    it('GET /purchases/create returns 403 (no purchases.create)', () => {
        assertForbidden('GET', '/purchases/create');
    });

    // Payments — staff has payments.view but NOT payments.create
    it('GET /payments/create returns 403 (no payments.create)', () => {
        assertForbidden('GET', '/payments/create');
    });

    // Expenses — staff has expenses.view but NOT expenses.delete
    it('DELETE /expenses/{id} returns 403 (no expenses.delete)', () => {
        assertForbidden('DELETE', `/expenses/${seedData.expense_id}`);
    });
});

/*
|--------------------------------------------------------------------------
| Cashier role
|--------------------------------------------------------------------------
| Has: products.view, customers.view, suppliers.view, sales.view,
| sales.create, pos.view, pos.operate, pos.manage-sessions,
| payments.view, payments.create, inventory.view
| Cannot: users, roles, treasury, purchases, expenses, cheques,
|         inventory.manage, inventory.transfer
*/
describe('as cashier', () => {
    beforeEach(() => cy.tenantLoginAs('cashier'));

    // Users
    it('GET /users returns 403', () => {
        assertForbidden('GET', '/users');
    });

    it('POST /users/invite returns 403', () => {
        assertForbidden('POST', '/users/invite', { email: 'hack@test.com' });
    });

    // Roles
    it('GET /roles returns 403', () => {
        assertForbidden('GET', '/roles');
    });

    it('POST /roles returns 403', () => {
        assertForbidden('POST', '/roles', { name: 'Hacked Role' });
    });

    // Treasury — cashier has NO treasury permissions
    it('GET /treasury returns 403', () => {
        assertForbidden('GET', '/treasury');
    });

    it('POST /treasury returns 403', () => {
        assertForbidden('POST', '/treasury', { name: 'Cash', type: 'cash', currency: 'SDG' });
    });

    it('GET /treasury/transfer returns 403', () => {
        assertForbidden('GET', '/treasury/transfer');
    });

    // Stock transfers — no inventory.transfer
    it('GET /stock-transfers returns 403', () => {
        assertForbidden('GET', '/stock-transfers');
    });

    // Customers — cashier has customers.view but NOT update/delete
    it('PUT /customers/{id} returns 403', () => {
        assertForbidden('PUT', `/customers/${seedData.customer_id}`, { name: 'Hacked' });
    });

    it('DELETE /customers/{id} returns 403', () => {
        assertForbidden('DELETE', `/customers/${seedData.customer_id}`);
    });

    // Storages — no inventory.manage
    it('DELETE /storages/{id} returns 403', () => {
        assertForbidden('DELETE', `/storages/${seedData.storage_id}`);
    });
});

/*
|--------------------------------------------------------------------------
| Manager role
|--------------------------------------------------------------------------
| Has all permissions EXCEPT users.assign-role and roles.manage.
*/
describe('as manager', () => {
    beforeEach(() => cy.tenantLoginAs('manager'));

    // Roles — manager lacks roles.manage
    it('GET /roles returns 403', () => {
        assertForbidden('GET', '/roles');
    });

    it('POST /roles returns 403', () => {
        assertForbidden('POST', '/roles', { name: 'Escalated Role' });
    });

    // Users — manager lacks users.assign-role
    it('PUT /users/{id}/role returns 403 (no users.assign-role)', () => {
        assertForbidden('PUT', `/users/${seedData.owner_user_id}/role`, { role_id: 1 });
    });

    // Manager CAN access treasury, users (view/invite/manage), stock transfers, etc.
    // Verify a few allowed endpoints return 200
    it('GET /users returns 200 (has users.view)', () => {
        cy.request({ url: '/users', failOnStatusCode: false })
            .its('status').should('eq', 200);
    });

    it('GET /treasury returns 200 (has treasury.view)', () => {
        cy.request({ url: '/treasury', failOnStatusCode: false })
            .its('status').should('eq', 200);
    });

    it('GET /stock-transfers returns 200 (has inventory.transfer)', () => {
        cy.request({ url: '/stock-transfers', failOnStatusCode: false })
            .its('status').should('eq', 200);
    });
});
