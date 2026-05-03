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

    public function test_tenant_login_rejects_non_member_users(): void
    {
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'is_active' => true]);
        $user = User::factory()->create();

        $tenantBase = 'http://acme.'.config('app.domain');

        $this->post($tenantBase.'/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_tenant_login_allows_member_users(): void
    {
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'is_active' => true]);
        $user = User::factory()->create();
        $tenant->users()->attach($user, ['role' => 'owner']);

        $tenantBase = 'http://acme.'.config('app.domain');

        $this->post($tenantBase.'/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_tenant_login_page_is_accessible(): void
    {
        Tenant::create(['name' => 'Acme Corp', 'slug' => 'acme', 'is_active' => true]);

        $tenantBase = 'http://acme.'.config('app.domain');

        $this->get($tenantBase.'/login')->assertOk();
    }

    public function test_guest_on_subdomain_redirects_to_main_domain_login(): void
    {
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'is_active' => true]);

        $tenantBase = 'http://acme.'.config('app.domain');

        $this->get($tenantBase.'/dashboard')
            ->assertRedirect();
    }
}
