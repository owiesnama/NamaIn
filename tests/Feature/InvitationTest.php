<?php

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->tenant = Tenant::where('slug', 'test-org')->first();
    seedTenantRoles($this->tenant);
    $this->staffRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'staff')->first();
    $this->inviter = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->inviter, ['role' => 'owner', 'role_id' => Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'owner')->first()?->id, 'is_active' => true]);
});

it('shows accept page for a valid pending invitation', function () {
    $invitation = UserInvitation::create([
        'tenant_id' => $this->tenant->id,
        'invited_by' => $this->inviter->id,
        'role_id' => $this->staffRole->id,
        'email' => 'invited@example.com',
        'token' => Str::random(64),
        'expires_at' => now()->addDays(7),
    ]);

    $this->withoutTenantSubdomain()
        ->get(route('invitations.accept', $invitation->token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Invitations/Accept'));
});

it('redirects to login if invitation is already accepted', function () {
    $invitation = UserInvitation::create([
        'tenant_id' => $this->tenant->id,
        'invited_by' => $this->inviter->id,
        'role_id' => $this->staffRole->id,
        'email' => 'accepted@example.com',
        'token' => Str::random(64),
        'accepted_at' => now(),
        'expires_at' => now()->addDays(7),
    ]);

    $this->withoutTenantSubdomain()
        ->get(route('invitations.accept', $invitation->token))
        ->assertRedirect(route('login'));
});

it('shows expired page for an expired invitation', function () {
    $invitation = UserInvitation::create([
        'tenant_id' => $this->tenant->id,
        'invited_by' => $this->inviter->id,
        'role_id' => $this->staffRole->id,
        'email' => 'expired@example.com',
        'token' => Str::random(64),
        'expires_at' => now()->subDay(),
    ]);

    $this->withoutTenantSubdomain()
        ->get(route('invitations.accept', $invitation->token))
        ->assertInertia(fn ($page) => $page->component('Invitations/Expired'));
});

it('returns 404 for an invalid token', function () {
    $this->withoutTenantSubdomain()
        ->get(route('invitations.accept', 'invalid-token-that-does-not-exist'))
        ->assertNotFound();
});

it('allows a new user to accept an invitation and creates their account', function () {
    $token = Str::random(64);

    UserInvitation::create([
        'tenant_id' => $this->tenant->id,
        'invited_by' => $this->inviter->id,
        'role_id' => $this->staffRole->id,
        'email' => 'newmember@example.com',
        'token' => $token,
        'expires_at' => now()->addDays(7),
    ]);

    $this->withoutTenantSubdomain()
        ->post(route('invitations.accept.store', $token), [
            'name' => 'New Member',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertRedirect();

    $user = User::where('email', 'newmember@example.com')->first();
    expect($user)->not->toBeNull();
    expect($this->tenant->users()->where('users.id', $user->id)->exists())->toBeTrue();

    $invitation = UserInvitation::withoutGlobalScopes()->where('token', $token)->first();
    expect($invitation->isAccepted())->toBeTrue();
});

it('rejects an expired invitation on accept', function () {
    $token = Str::random(64);

    UserInvitation::create([
        'tenant_id' => $this->tenant->id,
        'invited_by' => $this->inviter->id,
        'role_id' => $this->staffRole->id,
        'email' => 'expiredaccept@example.com',
        'token' => $token,
        'expires_at' => now()->subDay(),
    ]);

    $this->withoutTenantSubdomain()
        ->post(route('invitations.accept.store', $token), [
            'name' => 'Someone',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertSessionHasErrors();
});

it('allows an existing user to accept an invitation and joins the tenant', function () {
    $existingUser = User::factory()->create(['email' => 'existing@example.com']);
    $token = Str::random(64);

    UserInvitation::create([
        'tenant_id' => $this->tenant->id,
        'invited_by' => $this->inviter->id,
        'role_id' => $this->staffRole->id,
        'email' => 'existing@example.com',
        'token' => $token,
        'expires_at' => now()->addDays(7),
    ]);

    $this->withoutTenantSubdomain()
        ->post(route('invitations.accept.store', $token), [
            'name' => 'Existing User',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertRedirect();

    // User should now belong to the tenant
    expect($this->tenant->users()->where('users.id', $existingUser->id)->exists())->toBeTrue();

    // User count should not increase (existing user)
    expect(User::where('email', 'existing@example.com')->count())->toBe(1);
});

it('sets current_tenant_id for an existing user without one on invitation acceptance', function () {
    $existingUser = User::factory()->create([
        'email' => 'notenant@example.com',
        'current_tenant_id' => null,
    ]);
    $token = Str::random(64);

    UserInvitation::create([
        'tenant_id' => $this->tenant->id,
        'invited_by' => $this->inviter->id,
        'role_id' => $this->staffRole->id,
        'email' => 'notenant@example.com',
        'token' => $token,
        'expires_at' => now()->addDays(7),
    ]);

    $this->withoutTenantSubdomain()
        ->post(route('invitations.accept.store', $token), [
            'name' => 'No Tenant User',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertRedirect();

    expect($existingUser->fresh()->current_tenant_id)->toBe($this->tenant->id);
});

// ────────────────────────────────────────────────────────
// UserInvitation model methods
// ────────────────────────────────────────────────────────

it('UserInvitation isExpired returns true for past expiry', function () {
    $invitation = UserInvitation::create([
        'tenant_id' => $this->tenant->id,
        'invited_by' => $this->inviter->id,
        'role_id' => $this->staffRole->id,
        'email' => 'expired-model@example.com',
        'token' => Str::random(64),
        'expires_at' => now()->subHour(),
    ]);

    expect($invitation->isExpired())->toBeTrue();
    expect($invitation->isPending())->toBeFalse();
});

it('UserInvitation isExpired returns false for future expiry', function () {
    $invitation = UserInvitation::create([
        'tenant_id' => $this->tenant->id,
        'invited_by' => $this->inviter->id,
        'role_id' => $this->staffRole->id,
        'email' => 'valid-model@example.com',
        'token' => Str::random(64),
        'expires_at' => now()->addDay(),
    ]);

    expect($invitation->isExpired())->toBeFalse();
    expect($invitation->isPending())->toBeTrue();
});

it('UserInvitation isAccepted returns true after acceptance', function () {
    $invitation = UserInvitation::create([
        'tenant_id' => $this->tenant->id,
        'invited_by' => $this->inviter->id,
        'role_id' => $this->staffRole->id,
        'email' => 'accepted-model@example.com',
        'token' => Str::random(64),
        'expires_at' => now()->addDays(7),
        'accepted_at' => now(),
    ]);

    expect($invitation->isAccepted())->toBeTrue();
    expect($invitation->isPending())->toBeFalse();
});

it('scopePending returns only non-expired and non-accepted invitations', function () {
    UserInvitation::create([
        'tenant_id' => $this->tenant->id,
        'invited_by' => $this->inviter->id,
        'role_id' => $this->staffRole->id,
        'email' => 'pending1@example.com',
        'token' => Str::random(64),
        'expires_at' => now()->addDays(7),
    ]);

    UserInvitation::create([
        'tenant_id' => $this->tenant->id,
        'invited_by' => $this->inviter->id,
        'role_id' => $this->staffRole->id,
        'email' => 'accepted-scope@example.com',
        'token' => Str::random(64),
        'expires_at' => now()->addDays(7),
        'accepted_at' => now(),
    ]);

    UserInvitation::create([
        'tenant_id' => $this->tenant->id,
        'invited_by' => $this->inviter->id,
        'role_id' => $this->staffRole->id,
        'email' => 'expired-scope@example.com',
        'token' => Str::random(64),
        'expires_at' => now()->subDay(),
    ]);

    $pending = UserInvitation::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->pending()->get();

    expect($pending)->toHaveCount(1);
    expect($pending->first()->email)->toBe('pending1@example.com');
});
