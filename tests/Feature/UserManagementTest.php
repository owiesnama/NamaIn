<?php

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserInvitation;
use App\Notifications\UserCredentialsNotification;
use App\Notifications\UserInvitedNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->tenant = Tenant::where('slug', 'test-org')->first();
    seedTenantRoles($this->tenant);
    $this->managerRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'manager')->first();
    $this->staffRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'staff')->first();
});

it('allows owner to view users index', function () {
    actingAsTenantUser(role: 'owner')
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Users/Index'));
});

it('denies staff from viewing users index', function () {
    actingAsTenantUser(role: 'staff')
        ->get(route('users.index'))
        ->assertForbidden();
});

it('allows owner to create a user directly and emails credentials', function () {
    Notification::fake();

    actingAsTenantUser(role: 'owner')
        ->post(route('users.store'), [
            'name' => 'Direct User',
            'email' => 'direct@example.com',
            'role_id' => $this->managerRole->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $user = User::where('email', 'direct@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->must_change_password)->toBeTrue();

    Notification::assertSentTo($user, UserCredentialsNotification::class);
});

it('does not expose the generated password in the session', function () {
    Notification::fake();

    actingAsTenantUser(role: 'owner')
        ->post(route('users.store'), [
            'name' => 'Direct User',
            'email' => 'direct@example.com',
            'role_id' => $this->managerRole->id,
        ])
        ->assertSessionMissing('createdUser');
});

it('allows owner to invite a user', function () {
    Notification::fake();

    actingAsTenantUser(role: 'owner')
        ->post(route('users.invite'), [
            'email' => 'newuser@example.com',
            'role_id' => $this->managerRole->id,
        ])
        ->assertRedirect();

    expect(UserInvitation::withoutGlobalScopes()->where('email', 'newuser@example.com')->exists())->toBeTrue();

    Notification::assertSentOnDemand(UserInvitedNotification::class);
});

it('denies staff from inviting a user', function () {
    actingAsTenantUser(role: 'staff')
        ->post(route('users.invite'), [
            'email' => 'newuser@example.com',
            'role_id' => $this->managerRole->id,
        ])
        ->assertForbidden();
});

it('cannot invite a user who is already a member', function () {
    $existingUser = User::factory()->create();
    $this->tenant->users()->attach($existingUser, ['role' => 'staff', 'role_id' => $this->staffRole->id, 'is_active' => true]);

    actingAsTenantUser(role: 'owner')
        ->post(route('users.invite'), [
            'email' => $existingUser->email,
            'role_id' => $this->managerRole->id,
        ])
        ->assertSessionHasErrors('email');
});

it('allows owner to disable a member', function () {
    $member = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($member, ['role' => 'staff', 'role_id' => $this->staffRole->id, 'is_active' => true]);

    actingAsTenantUser(role: 'owner')
        ->put(route('users.toggle-status', $member))
        ->assertRedirect();

    $pivot = $this->tenant->users()->where('users.id', $member->id)->first()?->pivot;
    expect((bool) $pivot?->is_active)->toBeFalse();
});

it('cannot disable the owner', function () {
    $ownerRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'owner')->first();
    $owner = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($owner, ['role' => 'owner', 'role_id' => $ownerRole->id, 'is_active' => true]);

    actingAsTenantUser(role: 'owner')
        ->put(route('users.toggle-status', $owner))
        ->assertSessionHasErrors();
});

it('allows owner to assign a role to a member', function () {
    $member = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($member, ['role' => 'staff', 'role_id' => $this->staffRole->id, 'is_active' => true]);

    actingAsTenantUser(role: 'owner')
        ->put(route('users.assign-role', $member), [
            'role_id' => $this->managerRole->id,
        ])
        ->assertRedirect();

    $pivot = $this->tenant->users()->where('users.id', $member->id)->first()?->pivot;
    expect($pivot?->role_id)->toBe($this->managerRole->id);
});

it('cannot assign the owner role to a member', function () {
    $ownerRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'owner')->first();
    $member = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($member, ['role' => 'staff', 'role_id' => $this->staffRole->id, 'is_active' => true]);

    actingAsTenantUser(role: 'owner')
        ->put(route('users.assign-role', $member), [
            'role_id' => $ownerRole->id,
        ])
        ->assertSessionHasErrors();
});

it('allows owner to remove a member from the organization', function () {
    $member = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($member, ['role' => 'staff', 'role_id' => $this->staffRole->id, 'is_active' => true]);

    actingAsTenantUser(role: 'owner')
        ->delete(route('users.destroy', $member))
        ->assertRedirect(route('users.index'));

    expect($this->tenant->users()->where('users.id', $member->id)->exists())->toBeFalse();
});

it('cannot remove the owner from the organization', function () {
    $ownerRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'owner')->first();
    $owner = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($owner, ['role' => 'owner', 'role_id' => $ownerRole->id, 'is_active' => true]);

    actingAsTenantUser(role: 'owner')
        ->delete(route('users.destroy', $owner))
        ->assertSessionHasErrors();
});

it('cannot remove yourself from the organization', function () {
    $ownerUser = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $ownerRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'owner')->first();
    $this->tenant->users()->attach($ownerUser, ['role' => 'owner', 'role_id' => $ownerRole->id, 'is_active' => true]);

    // Acting as the same user who is trying to remove themselves
    test()->actingAs($ownerUser)
        ->delete(route('users.destroy', $ownerUser))
        ->assertSessionHasErrors('user');
});

it('replaces a pending invitation when re-inviting the same email', function () {
    Notification::fake();

    $actingUser = actingAsTenantUser(role: 'owner');

    // First invitation
    $actingUser->post(route('users.invite'), [
        'email' => 'reinvite@example.com',
        'role_id' => $this->managerRole->id,
    ]);

    $firstInvitation = UserInvitation::withoutGlobalScopes()->where('email', 'reinvite@example.com')->first();

    // Re-invite same email
    $actingUser->post(route('users.invite'), [
        'email' => 'reinvite@example.com',
        'role_id' => $this->staffRole->id,
    ]);

    // Only one invitation should exist — the new one
    $remaining = UserInvitation::withoutGlobalScopes()->where('email', 'reinvite@example.com')->get();
    expect($remaining)->toHaveCount(1);
    expect($remaining->first()->id)->not->toBe($firstInvitation->id);
    expect($remaining->first()->role_id)->toBe($this->staffRole->id);
});

it('allows owner to resend credentials to a direct-created user', function () {
    Notification::fake();

    $member = User::factory()->create([
        'current_tenant_id' => $this->tenant->id,
        'must_change_password' => true,
    ]);
    $this->tenant->users()->attach($member, ['role' => 'staff', 'role_id' => $this->staffRole->id, 'is_active' => true]);

    actingAsTenantUser(role: 'owner')
        ->post(route('users.credentials.resend', $member))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($member, UserCredentialsNotification::class);
    expect($member->fresh()->must_change_password)->toBeTrue();
});

it('denies resending credentials when user has already set their own password', function () {
    Notification::fake();

    $member = User::factory()->create([
        'current_tenant_id' => $this->tenant->id,
        'must_change_password' => false,
    ]);
    $this->tenant->users()->attach($member, ['role' => 'staff', 'role_id' => $this->staffRole->id, 'is_active' => true]);

    actingAsTenantUser(role: 'owner')
        ->post(route('users.credentials.resend', $member))
        ->assertSessionHasErrors('user');

    Notification::assertNothingSent();
});

it('denies resending credentials to an inactive member', function () {
    Notification::fake();

    $member = User::factory()->create([
        'current_tenant_id' => $this->tenant->id,
        'must_change_password' => true,
    ]);
    $this->tenant->users()->attach($member, ['role' => 'staff', 'role_id' => $this->staffRole->id, 'is_active' => false]);

    actingAsTenantUser(role: 'owner')
        ->post(route('users.credentials.resend', $member))
        ->assertSessionHasErrors('user');

    Notification::assertNothingSent();
});

it('denies resending credentials to the owner', function () {
    Notification::fake();

    $ownerRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'owner')->first();
    $owner = User::factory()->create([
        'current_tenant_id' => $this->tenant->id,
        'must_change_password' => true,
    ]);
    $this->tenant->users()->attach($owner, ['role' => 'owner', 'role_id' => $ownerRole->id, 'is_active' => true]);

    actingAsTenantUser(role: 'owner')
        ->post(route('users.credentials.resend', $owner))
        ->assertSessionHasErrors('user');

    Notification::assertNothingSent();
});

it('denies resending credentials to yourself', function () {
    Notification::fake();

    $ownerUser = User::factory()->create([
        'current_tenant_id' => $this->tenant->id,
        'must_change_password' => false,
    ]);
    $ownerRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'owner')->first();
    $this->tenant->users()->attach($ownerUser, ['role' => 'owner', 'role_id' => $ownerRole->id, 'is_active' => true]);

    test()->actingAs($ownerUser)
        ->post(route('users.credentials.resend', $ownerUser))
        ->assertSessionHasErrors('user');

    Notification::assertNothingSent();
});

it('denies staff from resending credentials', function () {
    Notification::fake();

    $member = User::factory()->create([
        'current_tenant_id' => $this->tenant->id,
        'must_change_password' => true,
    ]);
    $this->tenant->users()->attach($member, ['role' => 'staff', 'role_id' => $this->staffRole->id, 'is_active' => true]);

    actingAsTenantUser(role: 'staff')
        ->post(route('users.credentials.resend', $member))
        ->assertForbidden();

    Notification::assertNothingSent();
});

it('exposes can_resend_credentials on members with must_change_password', function () {
    $member = User::factory()->create([
        'current_tenant_id' => $this->tenant->id,
        'must_change_password' => true,
    ]);
    $this->tenant->users()->attach($member, ['role' => 'staff', 'role_id' => $this->staffRole->id, 'is_active' => true]);

    actingAsTenantUser(role: 'owner')
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Users/Index')
            ->where('members', fn ($members) => collect($members)
                ->firstWhere('id', $member->id)['can_resend_credentials'] === true
            )
        );
});

it('does not expose can_resend_credentials when must_change_password is false', function () {
    $member = User::factory()->create([
        'current_tenant_id' => $this->tenant->id,
        'must_change_password' => false,
    ]);
    $this->tenant->users()->attach($member, ['role' => 'staff', 'role_id' => $this->staffRole->id, 'is_active' => true]);

    actingAsTenantUser(role: 'owner')
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Users/Index')
            ->where('members', fn ($members) => collect($members)
                ->firstWhere('id', $member->id)['can_resend_credentials'] === false
            )
        );
});
