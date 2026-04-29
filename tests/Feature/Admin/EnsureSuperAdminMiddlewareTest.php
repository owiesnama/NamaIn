<?php

use App\Models\User;

it('redirects guests to admin login', function () {
    $this->get(route('admin.dashboard'))
        ->assertRedirect('/__admin/login');
});

it('returns 403 for non-admin users authenticated via admin guard', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->markEmailAsVerified();

    $this->actingAs($user, 'admin')
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

it('allows admin users to access admin routes', function () {
    actingAsSuperAdmin();

    $this->get(route('admin.dashboard'))
        ->assertOk();
});

it('does not allow web-guard-only users to access admin routes', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $user->markEmailAsVerified();

    // Authenticate on the web guard only (not admin guard)
    $this->actingAs($user, 'web')
        ->get(route('admin.dashboard'))
        ->assertRedirect('/__admin/login');
});
