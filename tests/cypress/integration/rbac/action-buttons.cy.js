/**
 * E2E tests: action button visibility per role
 *
 * Tests that create/edit/delete/transfer/adjust buttons are hidden
 * when the user's role lacks the corresponding permission.
 *
 * Pages with permission-gated buttons:
 *   Users/Index     — users.invite, users.manage, users.assign-role
 *   Treasury/Index  — treasury.create, treasury.transfer
 *   Treasury/Show   — treasury.adjust, treasury.transfer
 *   StockTransfers  — inventory.transfer
 *   Storages/Index  — inventory.manage, inventory.transfer
 *   Customers/Index — customers.create, customers.update, customers.delete
 */

before(() => {
    cy.refreshDatabase();

    // Seed as owner to create test data
    cy.tenantLoginAs('owner');

    cy.php(`
        $tenant = App\\Models\\Tenant::where('slug', 'cypress-test')->first();
        app()->instance('currentTenant', $tenant);

        $customer = App\\Models\\Customer::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'RBAC Test Customer',
        ]);

        $storage = App\\Models\\Storage::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'RBAC Test Storage',
        ]);

        $treasury = App\\Models\\TreasuryAccount::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'RBAC Test Account',
        ]);

        // Seed a non-owner user so the Users page has someone to Edit/Remove
        $staffUser = App\\Models\\User::factory()->create([
            'name' => 'Staff Member For RBAC',
            'current_tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);
        $staffRole = App\\Models\\Role::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('slug', 'staff')
            ->first();
        $tenant->users()->attach($staffUser, [
            'role' => 'staff',
            'role_id' => $staffRole?->id,
            'is_active' => true,
        ]);

        return ['treasury_id' => $treasury->id];
    `).then((data) => {
        Cypress.env('treasuryId', data.treasury_id);
    });
});

/*
|--------------------------------------------------------------------------
| Users page
|--------------------------------------------------------------------------
*/
describe('Users page', () => {
    describe('as owner', () => {
        beforeEach(() => cy.tenantLoginAs('owner'));

        it('shows Invite Member and Add User buttons', () => {
            cy.visit('/users');
            cy.contains('button', 'Invite Member').should('exist');
            cy.contains('button', 'Add User').should('exist');
        });

        it('shows Edit and Remove buttons for non-owner members', () => {
            cy.visit('/users');
            cy.contains('tr', 'Staff Member For RBAC').within(() => {
                cy.contains('button', 'Edit').should('exist');
                cy.contains('button', 'Remove').should('exist');
            });
        });
    });

    describe('as manager', () => {
        beforeEach(() => cy.tenantLoginAs('manager'));

        it('shows Invite and Add User buttons (has users.invite)', () => {
            cy.visit('/users');
            cy.contains('button', 'Invite Member').should('exist');
            cy.contains('button', 'Add User').should('exist');
        });

        it('hides Role select in edit modal (no users.assign-role)', () => {
            cy.visit('/users');
            cy.contains('button', 'Edit').first().click();
            cy.contains('label', 'Role').should('not.exist');
            cy.contains('label', 'Status').should('exist');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Treasury Index page
|--------------------------------------------------------------------------
*/
describe('Treasury Index page', () => {
    describe('as owner', () => {
        beforeEach(() => cy.tenantLoginAs('owner'));

        it('shows New Account and Transfer buttons', () => {
            cy.visit('/treasury');
            cy.contains('New Account').should('exist');
            cy.contains('Transfer').should('exist');
        });
    });

    describe('as staff (treasury.view only)', () => {
        beforeEach(() => cy.tenantLoginAs('staff'));

        it('hides New Account button (no treasury.create)', () => {
            cy.visit('/treasury');
            cy.contains('New Account').should('not.exist');
        });

        it('hides Transfer button (no treasury.transfer)', () => {
            cy.visit('/treasury');
            cy.contains('Transfer').should('not.exist');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Treasury Show page
|--------------------------------------------------------------------------
*/
describe('Treasury Show page', () => {
    describe('as owner', () => {
        beforeEach(() => cy.tenantLoginAs('owner'));

        it('shows Adjust Balance and Transfer buttons', () => {
            cy.visit(`/treasury/${Cypress.env('treasuryId')}`);
            cy.contains('button', 'Adjust Balance').should('exist');
            cy.contains('Transfer').should('exist');
        });
    });

    describe('as staff (treasury.view only)', () => {
        beforeEach(() => cy.tenantLoginAs('staff'));

        it('hides Adjust Balance button (no treasury.adjust)', () => {
            cy.visit(`/treasury/${Cypress.env('treasuryId')}`);
            cy.contains('button', 'Adjust Balance').should('not.exist');
        });

        it('hides Transfer button (no treasury.transfer)', () => {
            cy.visit(`/treasury/${Cypress.env('treasuryId')}`);
            cy.contains('Transfer').should('not.exist');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Stock Transfers page
|--------------------------------------------------------------------------
*/
describe('Stock Transfers page', () => {
    describe('as owner', () => {
        beforeEach(() => cy.tenantLoginAs('owner'));

        it('shows New Transfer button', () => {
            cy.visit('/stock-transfers');
            cy.contains('New Transfer').should('exist');
        });
    });

    // Staff and cashier cannot access /stock-transfers at all (backend 403).
    // Manager has inventory.transfer so the button is visible.
    describe('as manager', () => {
        beforeEach(() => cy.tenantLoginAs('manager'));

        it('shows New Transfer button (has inventory.transfer)', () => {
            cy.visit('/stock-transfers');
            cy.contains('New Transfer').should('exist');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Storages page
|--------------------------------------------------------------------------
*/
describe('Storages page', () => {
    describe('as owner', () => {
        beforeEach(() => cy.tenantLoginAs('owner'));

        it('shows create storage button', () => {
            cy.visit('/storages');
            cy.contains('button', 'Add New').should('exist');
        });
    });

    describe('as staff (inventory.view only)', () => {
        beforeEach(() => cy.tenantLoginAs('staff'));

        it('hides create storage button (no inventory.manage)', () => {
            cy.visit('/storages');
            cy.contains('button', 'Add New').should('not.exist');
        });
    });

    describe('as cashier (inventory.view only)', () => {
        beforeEach(() => cy.tenantLoginAs('cashier'));

        it('hides create storage button (no inventory.manage)', () => {
            cy.visit('/storages');
            cy.contains('button', 'Add New').should('not.exist');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Customers page
|--------------------------------------------------------------------------
*/
describe('Customers page', () => {
    describe('as owner', () => {
        beforeEach(() => cy.tenantLoginAs('owner'));

        it('shows Add New button', () => {
            cy.visit('/customers');
            cy.contains('button', 'Add New').should('exist');
        });
    });

    describe('as staff (customers.view only)', () => {
        beforeEach(() => cy.tenantLoginAs('staff'));

        it('hides Add New button (no customers.create)', () => {
            cy.visit('/customers');
            cy.contains('button', 'Add New').should('not.exist');
        });
    });

    describe('as cashier (customers.view only)', () => {
        beforeEach(() => cy.tenantLoginAs('cashier'));

        it('hides Add New button (no customers.create)', () => {
            cy.visit('/customers');
            cy.contains('button', 'Add New').should('not.exist');
        });
    });
});
