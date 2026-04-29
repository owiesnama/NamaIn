/**
 * Log in as a super-admin user (role = 'admin').
 *
 * The session is created on the tenant subdomain (where Cypress endpoints
 * are served), then validated on the main domain so the cookie is
 * established for both origins.
 *
 * @example
 *   beforeEach(() => cy.adminLogin());
 *   it('visits admin dashboard', () => cy.visit('https://namain.test/admin'));
 */
Cypress.Commands.add('adminLogin', () => {
    cy.session('admin', () => {
        cy.php(`
            $user = App\\Models\\User::factory()->create([
                'role' => 'admin',
                'email_verified_at' => now(),
            ]);

            // Log in via the admin guard server-side
            Illuminate\\Support\\Facades\\Auth::guard('admin')->login($user);
            session()->save();

            return ['email' => $user->email]
        `).then(() => {
            // Validate the session is active on the main domain
            cy.visit('https://namain.test/__admin');
            cy.url().should('include', '/__admin');
            cy.url().should('not.include', 'login');
        });
    });
});

/**
 * Set up a tenant with an owner user and log them in.
 *
 * Uses cy.session() to cache authentication state across tests so test
 * isolation (which clears cookies before each it()) doesn't break things.
 *
 * @param {string} slug
 *
 * @example
 *   beforeEach(() => cy.tenantLogin());
 *   it('visits products', () => cy.visit('/products'));
 */
Cypress.Commands.add('tenantLogin', (slug = 'cypress-test') => {
    cy.session(slug, () => {
        cy.php(`
            $tenant = App\\Models\\Tenant::firstOrCreate(
                ['slug' => '${slug}'],
                ['name' => 'Cypress Test', 'is_active' => true]
            );

            app()->instance('currentTenant', $tenant);

            if (!App\\Models\\Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->exists()) {
                (new Database\\Seeders\\PermissionSeeder)->run();
                (new App\\Services\\DefaultRolesService)->seedForTenant($tenant);
            }

            $user = App\\Models\\User::factory()->create([
                'current_tenant_id' => $tenant->id,
                'email_verified_at' => now(),
            ]);

            $role = App\\Models\\Role::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('slug', 'owner')
                ->first();

            $tenant->users()->attach($user, [
                'role'    => 'owner',
                'role_id' => $role?->id,
                'is_active' => true,
            ]);

            // Set English locale so button text matches test assertions
            App\\Models\\Preference::updateOrCreate(
                ['key' => 'language', 'tenant_id' => $tenant->id],
                ['value' => 'en']
            );
            App\\Services\\TenantCache::forget('preferences');

            return ['slug' => $tenant->slug, 'email' => $user->email]
        `).then((result) => {
            cy.login({ attributes: { email: result.email } });
        });
    });
});

/**
 * Set up a tenant and log in as a user with a specific role.
 *
 * Uses cy.session() with a role-specific key so each role is cached
 * independently and doesn't interfere with other role sessions.
 *
 * @param {string} roleSlug  One of: owner, manager, cashier, staff (or any custom role slug)
 * @param {string} slug      Tenant slug (defaults to 'cypress-test')
 *
 * @example
 *   beforeEach(() => cy.tenantLoginAs('cashier'));
 *   beforeEach(() => cy.tenantLoginAs('staff'));
 */
Cypress.Commands.add('tenantLoginAs', (roleSlug, slug = 'cypress-test') => {
    cy.session(`${slug}-${roleSlug}`, () => {
        cy.php(`
            $tenant = App\\Models\\Tenant::firstOrCreate(
                ['slug' => '${slug}'],
                ['name' => 'Cypress Test', 'is_active' => true]
            );

            app()->instance('currentTenant', $tenant);

            if (!App\\Models\\Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->exists()) {
                (new Database\\Seeders\\PermissionSeeder)->run();
                (new App\\Services\\DefaultRolesService)->seedForTenant($tenant);
            }

            $user = App\\Models\\User::factory()->create([
                'current_tenant_id' => $tenant->id,
                'email_verified_at' => now(),
            ]);

            $role = App\\Models\\Role::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('slug', '${roleSlug}')
                ->first();

            $tenant->users()->attach($user, [
                'role'    => '${roleSlug}',
                'role_id' => $role?->id,
                'is_active' => true,
            ]);

            App\\Models\\Preference::updateOrCreate(
                ['key' => 'language', 'tenant_id' => $tenant->id],
                ['value' => 'en']
            );
            App\\Services\\TenantCache::forget('preferences');

            return ['slug' => $tenant->slug, 'email' => $user->email]
        `).then((result) => {
            cy.login({ attributes: { email: result.email } });
        });
    });
});
