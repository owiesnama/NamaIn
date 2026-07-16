<?php

use App\Enums\NumeralSystem;
use App\Models\Preference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;

uses(RefreshDatabase::class);

test('the default numeral system follows the locale', function () {
    expect(NumeralSystem::defaultForLocale('ar'))->toBe(NumeralSystem::Arabic)
        ->and(NumeralSystem::defaultForLocale('ar_EG'))->toBe(NumeralSystem::Arabic)
        ->and(NumeralSystem::defaultForLocale('en'))->toBe(NumeralSystem::Latin);
});

test('each system maps to the Intl locale that renders it', function () {
    expect(NumeralSystem::Arabic->intlLocale())->toBe('ar-SA')
        ->and(NumeralSystem::Latin->intlLocale())->toBe('en-US');
});

test('the shared numerals prop defaults to the language when unset', function () {
    App::setLocale('ar');
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('preferences.numerals', 'arabic'));
});

test('an explicit numerals preference overrides the language default', function () {
    App::setLocale('ar');
    Preference::create(['key' => 'numerals', 'value' => 'latin']);
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('preferences.numerals', 'latin'));
});

test('the numerals preference is validated against the enum', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('preferences.update'), ['numerals' => 'roman'])
        ->assertSessionHasErrors('numerals');

    $this->post(route('preferences.update'), ['numerals' => 'latin'])
        ->assertSessionDoesntHaveErrors('numerals');

    $this->assertDatabaseHas('preferences', ['key' => 'numerals', 'value' => 'latin']);
});
