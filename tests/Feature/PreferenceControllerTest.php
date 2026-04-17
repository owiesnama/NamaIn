<?php

use App\Models\Preference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
