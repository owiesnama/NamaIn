/**
 * E2E tests: Super-Admin Panel
 *
 * Admin routes live on the main domain (namain.test/admin),
 * not the tenant subdomain, so we use absolute URLs.
 */

const ADMIN_URL = 'https://namain.test/__admin';

before(() => {
    cy.refreshDatabase();
});

describe('Admin Access Control', () => {
    it('redirects unauthenticated users to login', () => {
        cy.request({
            url: `${ADMIN_URL}`,
            failOnStatusCode: false,
            followRedirect: false,
        }).then((response) => {
            expect(response.status).to.be.oneOf([302, 303]);
        });
    });

    it('non-admin users are redirected to admin login', () => {
        cy.tenantLogin();

        cy.request({
            url: `${ADMIN_URL}`,
            failOnStatusCode: false,
            followRedirect: false,
        }).then((response) => {
            // Non-admin users are not authenticated on the admin guard,
            // so they get redirected to the admin login page
            expect(response.status).to.be.oneOf([302, 303]);
        });
    });

    it('allows admin users to access the dashboard', () => {
        cy.adminLogin();

        cy.visit(`${ADMIN_URL}`);
        cy.get('h2').contains('Dashboard').should('be.visible');
    });
});

describe('Admin Dashboard', () => {
    beforeEach(() => cy.adminLogin());

    it('displays platform stats', () => {
        cy.visit(`${ADMIN_URL}`);

        cy.contains('Total Tenants').should('be.visible');
        cy.contains('Total Users').should('be.visible');
    });
});

describe('Tenant Management', () => {
    beforeEach(() => cy.adminLogin());

    it('lists tenants on the index page', () => {
        cy.visit(`${ADMIN_URL}/tenants`);

        cy.get('h2').contains('Tenants').should('be.visible');
        cy.contains('Manage all organizations').should('be.visible');
        cy.contains('button', 'Create Tenant').should('be.visible');
    });

    it('creates a new tenant via modal', () => {
        cy.php(`
            $user = App\\Models\\User::factory()->create(['email_verified_at' => now()]);
            return ['email' => $user->email]
        `).then((result) => {
            cy.visit(`${ADMIN_URL}/tenants`);

            cy.contains('button', 'Create Tenant').click();

            // Modal should appear
            cy.get('h3').contains('Create Tenant').should('be.visible');

            // Fill the form — target inputs inside the modal
            cy.get('form').within(() => {
                cy.get('input').eq(0).clear().type('Cypress Admin Tenant');
                cy.get('input').eq(1).clear().type('cypress-admin-tenant');
                cy.get('input').eq(2).clear().type(result.email);
                cy.contains('button', 'Create').click();
            });

            // Tenant should appear in the table after successful creation
            cy.contains('td', 'Cypress Admin Tenant').should('be.visible');
            cy.contains('td', 'cypress-admin-tenant').should('be.visible');
        });
    });

    it('shows the tenant detail page', () => {
        cy.visit(`${ADMIN_URL}/tenants`);

        // Click the first tenant name link
        cy.get('table tbody tr').first().find('a').first().click();

        cy.get('h2').should('be.visible');
        cy.contains('Members').should('be.visible');
    });

    it('toggles tenant active status', () => {
        // Create a tenant to toggle
        cy.php(`
            $t = App\\Models\\Tenant::factory()->create(['name' => 'Toggle Target', 'is_active' => true]);
            return ['id' => $t->id]
        `).then(() => {
            cy.visit(`${ADMIN_URL}/tenants`);

            // Find the Toggle Target row and click its deactivate button
            cy.contains('tr', 'Toggle Target').within(() => {
                cy.get('button[title="Deactivate"]').click();
            });

            // After page refreshes, the tenant should show Inactive
            cy.contains('tr', 'Toggle Target').contains('Inactive').should('be.visible');
        });
    });

    it('searches tenants by name', () => {
        cy.php(`
            App\\Models\\Tenant::factory()->create(['name' => 'SearchableOrg', 'slug' => 'searchable-org']);
        `);

        cy.visit(`${ADMIN_URL}/tenants`);

        cy.get('input[placeholder="Search tenants..."]').type('SearchableOrg');
        cy.wait(500);

        cy.contains('td', 'SearchableOrg').should('be.visible');
    });

    it('filters tenants by status', () => {
        cy.visit(`${ADMIN_URL}/tenants`);

        cy.get('select').select('active');
        cy.wait(500);

        // Should not show any inactive badges in the table
        cy.get('table tbody').should('not.contain', 'Inactive');
    });
});

describe('Tenant Detail & User Management', () => {
    beforeEach(() => cy.adminLogin());

    it('shows members table and action buttons', () => {
        // Create a tenant with an owner
        cy.php(`
            $tenant = App\\Models\\Tenant::factory()->create();
            (new Database\\Seeders\\PermissionSeeder)->run();
            (new App\\Services\\DefaultRolesService)->seedForTenant($tenant);

            $owner = App\\Models\\User::factory()->create(['current_tenant_id' => $tenant->id]);
            $role = App\\Models\\Role::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('slug', 'owner')
                ->first();
            $tenant->users()->attach($owner, ['role' => 'owner', 'role_id' => $role->id, 'is_active' => true]);

            return ['id' => $tenant->id]
        `).then((result) => {
            cy.visit(`${ADMIN_URL}/tenants/${result.id}`);

            cy.contains('Members').should('be.visible');
            cy.contains('button', 'Add User').should('be.visible');
            cy.contains('Transfer Ownership').should('be.visible');

            // Should have at least one member row with per-user impersonate button
            cy.get('table tbody tr').should('have.length.at.least', 1);
            cy.get('table tbody tr').first().within(() => {
                cy.get('button[title="Impersonate"]').should('be.visible');
            });
        });
    });

    it('opens the add user modal', () => {
        cy.php(`
            $tenant = App\\Models\\Tenant::factory()->create();
            (new Database\\Seeders\\PermissionSeeder)->run();
            (new App\\Services\\DefaultRolesService)->seedForTenant($tenant);

            $owner = App\\Models\\User::factory()->create(['current_tenant_id' => $tenant->id]);
            $role = App\\Models\\Role::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('slug', 'owner')
                ->first();
            $tenant->users()->attach($owner, ['role' => 'owner', 'role_id' => $role->id, 'is_active' => true]);

            return ['id' => $tenant->id]
        `).then((result) => {
            cy.visit(`${ADMIN_URL}/tenants/${result.id}`);

            cy.contains('button', 'Add User').click();
            cy.get('h3').contains('Add User to Tenant').should('be.visible');
        });
    });
});

describe('Impersonation', () => {
    it('impersonate button is visible per user row on tenant detail', () => {
        cy.adminLogin();

        cy.php(`
            $tenant = App\\Models\\Tenant::factory()->create(['name' => 'Impersonate Me']);
            (new Database\\Seeders\\PermissionSeeder)->run();
            (new App\\Services\\DefaultRolesService)->seedForTenant($tenant);

            $owner = App\\Models\\User::factory()->create(['current_tenant_id' => $tenant->id]);
            $role = App\\Models\\Role::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('slug', 'owner')
                ->first();
            $tenant->users()->attach($owner, ['role' => 'owner', 'role_id' => $role->id, 'is_active' => true]);

            return ['id' => $tenant->id]
        `).then((result) => {
            cy.visit(`${ADMIN_URL}/tenants/${result.id}`);

            // Each user row should have an impersonate button (icon-only with title)
            cy.get('table tbody tr').first().within(() => {
                cy.get('button[title="Impersonate"]').should('be.visible');
            });
        });
    });

    it('impersonation endpoint redirects to tenant subdomain', () => {
        cy.adminLogin();

        cy.php(`
            $tenant = App\\Models\\Tenant::factory()->create(['name' => 'Impersonate Target', 'slug' => 'imp-target']);
            (new Database\\Seeders\\PermissionSeeder)->run();
            (new App\\Services\\DefaultRolesService)->seedForTenant($tenant);

            $owner = App\\Models\\User::factory()->create(['current_tenant_id' => $tenant->id]);
            $role = App\\Models\\Role::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('slug', 'owner')
                ->first();
            $tenant->users()->attach($owner, ['role' => 'owner', 'role_id' => $role->id, 'is_active' => true]);

            return ['tenant_id' => $tenant->id, 'owner_id' => $owner->id]
        `).then((result) => {
            cy.csrfToken().then((token) => {
                cy.request({
                    method: 'POST',
                    url: `${ADMIN_URL}/tenants/${result.tenant_id}/users/${result.owner_id}/impersonate`,
                    body: { _token: token },
                    followRedirect: false,
                }).then((response) => {
                    expect(response.status).to.be.oneOf([302, 303]);
                    expect(response.redirectedToUrl || response.headers.location).to.include('imp-target.namain.test');
                });
            });
        });
    });
});
