<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    test()->withoutTenantSubdomain();
});

test('user can register with a new tenant', function () {
    $response = $this->post('/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'tenant_name' => 'Acme Corp',
        'tenant_slug' => 'acme-corp',
    ]);

    $this->assertAuthenticated();

    $user = User::where('email', 'john@example.com')->first();
    expect($user)->not->toBeNull();

    $tenant = Tenant::where('slug', 'acme-corp')->first();
    expect($tenant)->not->toBeNull();
    expect($tenant->name)->toBe('Acme Corp');
    expect($user->current_tenant_id)->toBe($tenant->id);
    expect($user->belongsToTenant($tenant))->toBeTrue();
    expect($tenant->users()->first()->pivot->role)->toBe('owner');
});

test('registration requires tenant name and slug', function () {
    $this->post('/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors(['tenant_name', 'tenant_slug']);
});

test('tenant slug must be unique', function () {
    Tenant::create(['name' => 'Existing', 'slug' => 'taken', 'is_active' => true]);

    $this->post('/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'tenant_name' => 'My Org',
        'tenant_slug' => 'taken',
    ])->assertSessionHasErrors(['tenant_slug']);
});

test('tenant slug must be alpha dash', function () {
    $this->post('/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'tenant_name' => 'My Org',
        'tenant_slug' => 'invalid slug!',
    ])->assertSessionHasErrors(['tenant_slug']);
});

test('registration redirects to the created tenant dashboard subdomain', function () {
    $response = $this->post('/register', [
        'name' => 'Redirect User',
        'email' => 'redirect@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'tenant_name' => 'Is Company',
        'tenant_slug' => 'is',
    ]);

    $response->assertRedirect('https://is.namain.test/dashboard');
});

test('tenant switch route accepts tenant slug values', function () {
    $tenant = Tenant::create(['name' => 'Is Tenant', 'slug' => 'is', 'is_active' => true]);
    $user = User::factory()->create();
    $tenant->users()->attach($user, ['role' => 'owner']);

    $response = $this->actingAs($user)
        ->post(route('tenants.switch', ['tenant' => $tenant->slug]));

    $response->assertRedirect('https://is.namain.test/dashboard');
});

test('tenant switch returns inertia location to force full browser refresh', function () {
    $tenant = Tenant::create(['name' => 'Is Tenant', 'slug' => 'is', 'is_active' => true]);
    $user = User::factory()->create();
    $tenant->users()->attach($user, ['role' => 'owner']);

    $response = $this->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->post(route('tenants.switch', ['tenant' => $tenant->slug]));

    $response->assertStatus(409);
    $response->assertHeader('X-Inertia-Location', 'https://is.namain.test/dashboard');
});

test('tenant switch always redirects to configured app domain regardless of request host', function () {
    $tenant = Tenant::create(['name' => 'Is Tenant', 'slug' => 'is', 'is_active' => true]);
    $user = User::factory()->create();
    $tenant->users()->attach($user, ['role' => 'owner']);

    $response = $this->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->post('https://erp.example.com/tenants/is/select');

    $response->assertStatus(409);
    $response->assertHeader('X-Inertia-Location', 'https://is.'.config('app.domain').'/dashboard');
});
