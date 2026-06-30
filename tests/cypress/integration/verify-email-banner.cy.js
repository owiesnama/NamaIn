/**
 * E2E tests: non-blocking email verification banner
 *
 * Verifies that unverified users can register/log in and reach the tenant
 * dashboard (no blocking interstitial), that the dashboard shows a dismissible
 * "please verify" banner with a working resend action, and that verified users
 * never see the banner.
 */

before(() => {
    Cypress.session.clearAllSavedSessions();
    cy.refreshDatabase();
});

/**
 * Create (or reset) an unverified owner on the cypress tenant and log them in.
 */
function loginUnverified() {
    return cy
        .php(`
            $tenant = App\\Models\\Tenant::firstOrCreate(
                ['slug' => 'cypress'],
                ['name' => 'Cypress Test', 'is_active' => true]
            );

            app()->instance('currentTenant', $tenant);

            if (!App\\Models\\Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->exists()) {
                (new App\\Services\\OnBoarding\\DefaultRolesService)->seedForTenant($tenant);
            }

            $user = App\\Models\\User::firstOrCreate(
                ['email' => 'unverified@cypress.test'],
                ['name' => 'Unverified Owner', 'password' => bcrypt('password')]
            );
            $user->forceFill([
                'current_tenant_id' => $tenant->id,
                'email_verified_at' => null,
            ])->save();

            $role = App\\Models\\Role::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('slug', 'owner')
                ->first();

            if (!$tenant->users()->where('users.id', $user->id)->exists()) {
                $tenant->users()->attach($user, [
                    'role'      => 'owner',
                    'role_id'   => $role?->id,
                    'is_active' => true,
                ]);
            }

            App\\Models\\Preference::updateOrCreate(
                ['key' => 'language', 'tenant_id' => $tenant->id],
                ['value' => 'en']
            );
            App\\Facades\\Cache::forget('preferences');

            return $user->email;
        `)
        .then((email) => {
            cy.login({ attributes: { email } });
        });
}

describe('Email verification banner (non-blocking)', () => {
    it('lets an unverified user reach the dashboard and shows the verify banner', () => {
        loginUnverified();

        cy.visit('/dashboard');

        // Reached the app — not bounced to a verification interstitial.
        cy.url().should('include', '/dashboard');
        cy.url().should('not.include', 'verify');
        cy.get('[data-cy=verify-email-banner]').should('be.visible');
    });

    it('resend action sends the email and shows success feedback', () => {
        loginUnverified();

        cy.visit('/dashboard');

        cy.get('[data-cy=resend-verification]').click();

        cy.get('[data-cy=verification-sent]').should('be.visible');
        cy.get('[data-cy=resend-verification]').should('be.disabled');
    });

    it('banner is dismissible for the session and reappears on reload while unverified', () => {
        loginUnverified();

        cy.visit('/dashboard');

        cy.get('[data-cy=dismiss-verify-banner]').click();
        cy.get('[data-cy=verify-email-banner]').should('not.exist');

        // Local dismissal only — a reload brings it back while still unverified.
        cy.reload();
        cy.get('[data-cy=verify-email-banner]').should('be.visible');
    });

    it('does not show the banner for a verified user', () => {
        cy.tenantLogin();

        cy.visit('/dashboard');

        cy.url().should('include', '/dashboard');
        cy.get('[data-cy=verify-email-banner]').should('not.exist');
    });
});
