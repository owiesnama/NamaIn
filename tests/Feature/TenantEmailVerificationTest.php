<?php

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->tenant = Tenant::where('slug', 'test-org')->first();
    seedTenantRoles($this->tenant);
});

// ── Tenant Login — unverified detection ──────────────────────────────────────

it('redirects an unverified user to the main domain verification notice', function () {
    $user = User::factory()->create([
        'current_tenant_id' => $this->tenant->id,
        'email_verified_at' => null,
    ]);
    $staffRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'staff')->first();
    $this->tenant->users()->attach($user, ['role' => 'staff', 'role_id' => $staffRole->id, 'is_active' => true]);

    // Inertia forms always send X-Inertia header; Inertia::location() returns 409 for these
    $response = $this->withHeaders(['X-Inertia' => 'true'])
        ->post(route('tenant.login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

    $response->assertStatus(409);
    $response->assertHeader('X-Inertia-Location', route('verification.notice'));
});

it('stores the tenant slug in session when an unverified user logs in', function () {
    $user = User::factory()->create([
        'current_tenant_id' => $this->tenant->id,
        'email_verified_at' => null,
    ]);
    $staffRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'staff')->first();
    $this->tenant->users()->attach($user, ['role' => 'staff', 'role_id' => $staffRole->id, 'is_active' => true]);

    $this->withHeaders(['X-Inertia' => 'true'])
        ->post(route('tenant.login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

    $this->assertEquals('test-org', session('verification_tenant'));
});

it('lets a verified user log in without interruption', function () {
    $user = User::factory()->create([
        'current_tenant_id' => $this->tenant->id,
    ]);
    $user->markEmailAsVerified();
    $staffRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'staff')->first();
    $this->tenant->users()->attach($user, ['role' => 'staff', 'role_id' => $staffRole->id, 'is_active' => true]);

    $response = $this->withHeaders(['X-Inertia' => 'true'])
        ->post(route('tenant.login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

    // Normal redirect to tenant dashboard — not the verification page
    $response->assertRedirect(tenant_route('dashboard', $this->tenant->slug));
    $this->assertNull(session('verification_tenant'));
});

// ── VerifyEmailResponse — post-verification routing ───────────────────────────

it('redirects to the stored tenant dashboard after email verification', function () {
    $user = User::factory()->create([
        'current_tenant_id' => $this->tenant->id,
        'email_verified_at' => null,
    ]);
    $staffRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'staff')->first();
    $this->tenant->users()->attach($user, ['role' => 'staff', 'role_id' => $staffRole->id, 'is_active' => true]);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    // Verification link is a plain browser GET (no X-Inertia header) → Inertia::location = 302
    $response = test()->withoutTenantSubdomain()
        ->actingAs($user)
        ->withSession(['verification_tenant' => $this->tenant->slug])
        ->get($verificationUrl);

    $response->assertRedirect(tenant_route('dashboard', $this->tenant->slug));
});

it('falls back to the single tenant dashboard when no verification_tenant in session', function () {
    $user = User::factory()->create([
        'current_tenant_id' => $this->tenant->id,
        'email_verified_at' => null,
    ]);
    $staffRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'staff')->first();
    $this->tenant->users()->attach($user, ['role' => 'staff', 'role_id' => $staffRole->id, 'is_active' => true]);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = test()->withoutTenantSubdomain()
        ->actingAs($user)
        ->get($verificationUrl);

    $response->assertRedirect(tenant_route('dashboard', $this->tenant->slug));
});

it('falls back to tenants select when user has multiple tenants and no verification_tenant in session', function () {
    $secondTenant = Tenant::create(['name' => 'Second Org', 'slug' => 'second-org', 'is_active' => true]);
    seedTenantRoles($secondTenant);

    $user = User::factory()->create([
        'current_tenant_id' => $this->tenant->id,
        'email_verified_at' => null,
    ]);

    $staffRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'staff')->first();
    $this->tenant->users()->attach($user, ['role' => 'staff', 'role_id' => $staffRole->id, 'is_active' => true]);

    $staffRole2 = Role::withoutGlobalScopes()->where('tenant_id', $secondTenant->id)->where('slug', 'staff')->first();
    $secondTenant->users()->attach($user, ['role' => 'staff', 'role_id' => $staffRole2->id, 'is_active' => true]);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = test()->withoutTenantSubdomain()
        ->actingAs($user)
        ->get($verificationUrl);

    $response->assertRedirect(route('tenants.select'));
});

it('falls back gracefully when verification_tenant slug does not belong to the user', function () {
    Tenant::create(['name' => 'Other Org', 'slug' => 'other-org', 'is_active' => true]);

    $user = User::factory()->create([
        'current_tenant_id' => $this->tenant->id,
        'email_verified_at' => null,
    ]);
    $staffRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'staff')->first();
    $this->tenant->users()->attach($user, ['role' => 'staff', 'role_id' => $staffRole->id, 'is_active' => true]);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    // Session has a slug the user doesn't belong to — falls back to their own single tenant
    $response = test()->withoutTenantSubdomain()
        ->actingAs($user)
        ->withSession(['verification_tenant' => 'other-org'])
        ->get($verificationUrl);

    $response->assertRedirect(tenant_route('dashboard', $this->tenant->slug));
});
