<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('tenant can be created', function () {
    $tenant = Tenant::create([
        'name' => 'Test Corp',
        'slug' => 'test-corp',
        'is_active' => true,
    ]);

    expect($tenant->name)->toBe('Test Corp');
    expect($tenant->slug)->toBe('test-corp');
    expect($tenant->is_active)->toBeTrue();
});

test('tenant can be deactivated', function () {
    $tenant = Tenant::factory()->create();

    $tenant->deactivate();

    expect($tenant->fresh()->isActive())->toBeFalse();
});

test('tenant can be activated', function () {
    $tenant = Tenant::factory()->inactive()->create();

    $tenant->activate();

    expect($tenant->fresh()->isActive())->toBeTrue();
});

test('tenant has users relationship', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();

    $tenant->users()->attach($user, ['role' => 'owner']);

    expect($tenant->users)->toHaveCount(1);
    expect($tenant->owner()->id)->toBe($user->id);
});
