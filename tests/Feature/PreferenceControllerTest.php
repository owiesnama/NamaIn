<?php

use App\Models\Preference;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('it displays the preferences page', function () {
    Preference::create(['key' => 'currency', 'value' => 'SDG']);

    $this->get(route('preferences.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Preferences/Show')
            ->has('preferences')
        );
});

test('it updates preferences', function () {
    $this->post(route('preferences.update'), [
        'currency' => 'USD',
        'language' => 'ar',
    ])->assertRedirect();

    $this->assertDatabaseHas('preferences', ['key' => 'currency', 'value' => 'USD']);
    $this->assertDatabaseHas('preferences', ['key' => 'language', 'value' => 'ar']);
});

test('it skips null values when updating preferences', function () {
    Preference::create(['key' => 'currency', 'value' => 'SDG']);

    $this->post(route('preferences.update'), [
        'currency' => null,
    ])->assertRedirect();

    $this->assertDatabaseHas('preferences', ['key' => 'currency', 'value' => 'SDG']);
});

test('preferences cache and locale are isolated per tenant', function () {
    $tenantOne = Tenant::create(['name' => 'Tenant One', 'slug' => 'tenant-one', 'is_active' => true]);
    $tenantTwo = Tenant::create(['name' => 'Tenant Two', 'slug' => 'tenant-two', 'is_active' => true]);

    $userOne = User::factory()->create(['current_tenant_id' => $tenantOne->id]);
    $userTwo = User::factory()->create(['current_tenant_id' => $tenantTwo->id]);

    $tenantOne->users()->attach($userOne, ['role' => 'owner']);
    $tenantTwo->users()->attach($userTwo, ['role' => 'owner']);

    Preference::create(['tenant_id' => $tenantOne->id, 'key' => 'language', 'value' => 'ar']);
    Preference::create(['tenant_id' => $tenantTwo->id, 'key' => 'language', 'value' => 'en']);

    URL::defaults(['tenant' => $tenantOne->slug]);
    $this->actingAs($userOne)
        ->get('http://tenant-one.'.config('app.domain').'/dashboard')
        ->assertInertia(fn ($page) => $page->where('locale', 'ar'));

    URL::defaults(['tenant' => $tenantTwo->slug]);
    $this->actingAs($userTwo)
        ->get('http://tenant-two.'.config('app.domain').'/dashboard')
        ->assertInertia(fn ($page) => $page->where('locale', 'en'));
});
