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
