<?php

use App\Models\User;

it('creates an admin user with options', function () {
    $this->artisan('admin:create', [
        '--name' => 'Admin User',
        '--email' => 'admin@example.com',
        '--password' => 'password123',
    ])->assertSuccessful();

    $user = User::where('email', 'admin@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Admin User');
    expect($user->isAdmin())->toBeTrue();
    expect($user->email_verified_at)->not->toBeNull();
});

it('promotes an existing user to admin', function () {
    $user = User::factory()->create(['email' => 'existing@example.com', 'role' => 'user']);

    $this->artisan('admin:create', [
        '--email' => 'existing@example.com',
    ])->assertSuccessful()
        ->expectsOutputToContain('Promoted');

    $user->refresh();
    expect($user->isAdmin())->toBeTrue();
});

it('handles already-admin user gracefully', function () {
    User::factory()->create(['email' => 'already@example.com', 'role' => 'admin']);

    $this->artisan('admin:create', [
        '--email' => 'already@example.com',
    ])->assertSuccessful()
        ->expectsOutputToContain('already an admin');
});

it('rejects short password for new user', function () {
    $this->artisan('admin:create', [
        '--name' => 'Admin',
        '--email' => 'short@example.com',
        '--password' => '123',
    ])->assertFailed();
});
