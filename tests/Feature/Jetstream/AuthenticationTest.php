<?php

namespace Tests\Feature\Jetstream;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\MainDomainTestCase;

class AuthenticationTest extends MainDomainTestCase
{
    use RefreshDatabase;

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

    public function test_login_route_is_not_available_on_tenant_subdomains(): void
    {
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'is_active' => true]);

        $tenantBase = 'http://acme.'.config('app.domain');

        $this->post($tenantBase.'/login', [
            'email' => 'user@example.com',
            'password' => 'password',
        ])->assertNotFound();

        $this->assertGuest();
    }

    public function test_guest_on_subdomain_redirects_to_main_domain_login(): void
    {
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'is_active' => true]);

        $tenantBase = 'http://acme.'.config('app.domain');

        $this->get($tenantBase.'/dashboard')
            ->assertRedirect();
    }
}
