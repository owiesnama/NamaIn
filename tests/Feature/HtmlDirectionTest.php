<?php

use App\Models\Preference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * RTL must be established on <html>, not on a nested div. Anything rendered
 * outside the Vue layout — body-level teleports, the native scrollbar, text
 * selection — resolves its direction against the root element.
 */
test('the document root is rtl under the arabic locale', function () {
    Preference::create(['key' => 'language', 'value' => 'ar']);
    $this->actingAs(User::factory()->create());

    $response = $this->get(route('dashboard'));

    $response->assertOk();
    expect($response->getContent())
        ->toContain('<html lang="ar" dir="rtl"');
});

test('the document root is ltr under the english locale', function () {
    Preference::create(['key' => 'language', 'value' => 'en']);
    $this->actingAs(User::factory()->create());

    $response = $this->get(route('dashboard'));

    $response->assertOk();
    expect($response->getContent())
        ->toContain('<html lang="en" dir="ltr"');
});
