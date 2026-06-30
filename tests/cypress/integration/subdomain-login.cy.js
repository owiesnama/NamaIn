/**
 * E2E tests: subdomain-based tenant login
 *
 * Verifies that visiting a tenant subdomain login page authenticates
 * the user directly into that tenant (skipping the tenant selector),
 * and that "register" links redirect to the root domain.
 */

const appDomain = 'namain.test';
const rootOrigin = `https://${appDomain}`;

before(() => {
    Cypress.session.clearAllSavedSessions();
    cy.refreshDatabase();
});

describe('Subdomain Login', () => {
    it('redirects unauthenticated users to /login on the same subdomain', () => {
        // Visit the tenant dashboard without logging in
        cy.visit('/dashboard', { failOnStatusCode: false });

        cy.url().should('include', '/login');
        // Should stay on the tenant subdomain, not redirect to root domain
        cy.url().should('include', 'cypress.');
    });

    it('logs in on tenant subdomain and lands on tenant dashboard', () => {
        cy.tenantLogin();

        cy.visit('/dashboard');

        cy.url().should('include', '/dashboard');
        cy.url().should('include', 'cypress.');
    });

    it('login form posts to current subdomain, not root domain', () => {
        // Set up tenant and user via PHP
        cy.php(`
            $tenant = App\\Models\\Tenant::firstOrCreate(
                ['slug' => 'cypress'],
                ['name' => 'Cypress Test', 'is_active' => true]
            );

            app()->instance('currentTenant', $tenant);

            if (!App\\Models\\Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->exists()) {
                (new App\\Services\\OnBoarding\\DefaultRolesService)->seedForTenant($tenant);
            }

            $user = App\\Models\\User::factory()->create([
                'current_tenant_id' => $tenant->id,
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
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

            App\\Models\\Preference::updateOrCreate(
                ['key' => 'language', 'tenant_id' => $tenant->id],
                ['value' => 'en']
            );
            App\\Facades\\Cache::forget('preferences');

            return $user->email;
        `).then((email) => {
            cy.visit('/login');
            cy.url().should('include', '/login');

            cy.get('input[type="email"]').type(email);
            cy.get('input[type="password"]').type('password');

            cy.get('form').submit();

            // Should land on the tenant dashboard, not the tenant selector
            cy.url().should('include', '/dashboard');
            cy.url().should('include', 'cypress.');
        });
    });

    it('subdomain login page does not show register links (auth is main-domain only)', () => {
        cy.visit('/login');

        // Since auth routes are restricted to the main domain,
        // the subdomain login page should not contain register links
        cy.contains('a', 'Register now').should('not.exist');
        cy.contains('a', 'Create Your Organization').should('not.exist');
    });
});
