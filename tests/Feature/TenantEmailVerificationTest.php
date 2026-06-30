<?php

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->tenant = Tenant::where('slug', 'test-org')->first();
    seedTenantRoles($this->tenant);
});

/**
 * Attach the given user to the test tenant as active staff.
 */
function attachStaff(User $user, Tenant $tenant): void
{
    $staffRole = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'staff')->first();
    $tenant->users()->attach($user, ['role' => 'staff', 'role_id' => $staffRole->id, 'is_active' => true]);
}

// ── Login is non-blocking ─────────────────────────────────────────────────────

it('lets an unverified user log in and land on the tenant dashboard', function () {
    $user = User::factory()->create([
        'current_tenant_id' => $this->tenant->id,
        'email_verified_at' => null,
    ]);
    attachStaff($user, $this->tenant);

    $response = $this->withHeaders(['X-Inertia' => 'true'])
        ->post(route('tenant.login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

    // No 409 redirect to the verification notice — straight to the dashboard.
    $response->assertRedirect(tenant_route('dashboard', $this->tenant->slug));
    expect(session('verification_tenant'))->toBeNull();
});

it('lets a verified user log in without interruption', function () {
    $user = User::factory()->create([
        'current_tenant_id' => $this->tenant->id,
    ]);
    $user->markEmailAsVerified();
    attachStaff($user, $this->tenant);

    $response = $this->withHeaders(['X-Inertia' => 'true'])
        ->post(route('tenant.login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

    $response->assertRedirect(tenant_route('dashboard', $this->tenant->slug));
});

it('lets an unverified user reach the dashboard and exposes the unverified flag to the frontend', function () {
    $user = User::factory()->create([
        'current_tenant_id' => $this->tenant->id,
        'email_verified_at' => null,
    ]);
    attachStaff($user, $this->tenant);

    $response = $this->actingAs($user)->get(tenant_route('dashboard', $this->tenant->slug));

    $response->assertStatus(200);

    $sharedUser = $response->viewData('page')['props']['user'];
    expect($sharedUser['verified'])->toBeFalse();
    expect($sharedUser['email_verified_at'])->toBeNull();
});

it('exposes the verified flag as true for a verified user', function () {
    $user = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $user->markEmailAsVerified();
    attachStaff($user, $this->tenant);

    $response = $this->actingAs($user)->get(tenant_route('dashboard', $this->tenant->slug));

    $response->assertStatus(200);
    expect($response->viewData('page')['props']['user']['verified'])->toBeTrue();
});

// ── Registration is non-blocking ──────────────────────────────────────────────

it('routes a freshly registered, unverified user straight to their tenant dashboard', function () {
    $response = test()->withoutTenantSubdomain()->post('/register', [
        'name' => 'New Owner',
        'email' => 'new-owner@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'tenant_name' => 'Fresh Org',
        'tenant_slug' => 'fresh-org',
    ]);

    $this->assertAuthenticated();

    $user = User::where('email', 'new-owner@example.com')->first();
    expect($user->hasVerifiedEmail())->toBeFalse();

    $response->assertRedirect(tenant_route('dashboard', 'fresh-org'));
});

// ── Verify / resend machinery still works ─────────────────────────────────────

it('still dispatches the verification notification on resend', function () {
    Notification::fake();

    $user = User::factory()->create([
        'current_tenant_id' => $this->tenant->id,
        'email_verified_at' => null,
    ]);
    attachStaff($user, $this->tenant);

    // Fortify's verification routes live on the main domain (config/fortify.php domain).
    test()->withoutTenantSubdomain()
        ->actingAs($user)
        ->post(route('verification.send'));

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('resends the verification notification from the tenant dashboard banner action', function () {
    Notification::fake();

    $user = User::factory()->create([
        'current_tenant_id' => $this->tenant->id,
        'email_verified_at' => null,
    ]);
    attachStaff($user, $this->tenant);

    $response = $this->actingAs($user)
        ->from(tenant_route('dashboard', $this->tenant->slug))
        ->post(tenant_route('verification.resend', $this->tenant->slug));

    $response->assertRedirect(tenant_route('dashboard', $this->tenant->slug));
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('does not resend the verification notification for an already verified user', function () {
    Notification::fake();

    $user = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $user->markEmailAsVerified();
    attachStaff($user, $this->tenant);

    $this->actingAs($user)->post(tenant_route('verification.resend', $this->tenant->slug));

    Notification::assertNothingSent();
});

it('still marks the user verified and routes to the stored tenant dashboard', function () {
    $user = User::factory()->create([
        'current_tenant_id' => $this->tenant->id,
        'email_verified_at' => null,
    ]);
    attachStaff($user, $this->tenant);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = test()->withoutTenantSubdomain()
        ->actingAs($user)
        ->withSession(['verification_tenant' => $this->tenant->slug])
        ->get($verificationUrl);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(tenant_route('dashboard', $this->tenant->slug));
});

it('falls back to the single tenant dashboard when no verification_tenant in session', function () {
    $user = User::factory()->create([
        'current_tenant_id' => $this->tenant->id,
        'email_verified_at' => null,
    ]);
    attachStaff($user, $this->tenant);

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

    attachStaff($user, $this->tenant);

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
    attachStaff($user, $this->tenant);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    // Session has a slug the user doesn't belong to — falls back to their own single tenant.
    $response = test()->withoutTenantSubdomain()
        ->actingAs($user)
        ->withSession(['verification_tenant' => 'other-org'])
        ->get($verificationUrl);

    $response->assertRedirect(tenant_route('dashboard', $this->tenant->slug));
});
