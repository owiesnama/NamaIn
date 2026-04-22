<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\DuskTestCase;
use Tests\TestCase;

uses(
    DuskTestCase::class,
    // Illuminate\Foundation\Testing\DatabaseMigrations::class,
)->in('Browser');

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(TestCase::class, RefreshDatabase::class)->beforeEach(function () {
    $tenant = Tenant::create(['name' => 'Test Org', 'slug' => 'test-org', 'is_active' => true]);
    app()->instance('currentTenant', $tenant);
    URL::defaults(['tenant' => $tenant->slug]);
})->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function actingAsTenantUser(?User $user = null): TestCase
{
    $tenant = Tenant::create(['name' => 'Test Org', 'slug' => 'test-org', 'is_active' => true]);

    $user = $user ?? User::factory()->create(['current_tenant_id' => $tenant->id]);
    $user->markEmailAsVerified();

    if (! $user->belongsToTenant($tenant)) {
        $tenant->users()->attach($user, ['role' => 'owner']);
    }

    if ($user->current_tenant_id !== $tenant->id) {
        $user->update(['current_tenant_id' => $tenant->id]);
    }

    URL::defaults(['tenant' => $tenant->slug]);
    app()->instance('currentTenant', $tenant);

    return test()->actingAs($user);
}
