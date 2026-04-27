<?php

namespace Tests\Feature\Jetstream;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $withTenantSubdomain = false;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('tenants.select'));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_login_on_tenant_subdomain_redirects_to_tenant_dashboard(): void
    {
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'is_active' => true]);
        $user = User::factory()->create();
        $this->seedTenantRoles($tenant);

        $ownerRole = Role::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('slug', 'owner')
            ->first();

        $tenant->users()->attach($user, ['role' => 'owner', 'role_id' => $ownerRole?->id]);

        $tenantBase = 'http://acme.'.config('app.domain');

        $response = $this->post($tenantBase.'/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();

        $expectedUrl = parse_url(config('app.url'), PHP_URL_SCHEME).'://acme.'.config('app.domain').'/dashboard';
        $response->assertRedirect($expectedUrl);
    }

    public function test_login_on_subdomain_rejects_user_not_belonging_to_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'Other Org', 'slug' => 'other-org', 'is_active' => true]);
        $user = User::factory()->create();

        $tenantBase = 'http://other-org.'.config('app.domain');

        $response = $this->post($tenantBase.'/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_guest_redirect_preserves_subdomain(): void
    {
        $tenantBase = 'http://acme.'.config('app.domain');

        $response = $this->get($tenantBase.'/dashboard');

        $response->assertRedirect($tenantBase.'/login');
    }
}
