<?php

use App\Models\Preference;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Re-run the data migration's up() against seeded rows. Under RefreshDatabase
 * the migration first runs on an empty table, so we invoke it explicitly here
 * to exercise the normalisation logic.
 */
function runCurrencyNormalization(): void
{
    $migration = require database_path(
        'migrations/2026_07_13_134448_normalize_currency_preference_value.php'
    );

    $migration->up();
}

function makeTenant(string $slug): Tenant
{
    return Tenant::create(['name' => $slug, 'slug' => $slug, 'is_active' => true]);
}

test('it upper-cases and trims stored currency codes', function () {
    $one = makeTenant('one');
    $two = makeTenant('two');
    Preference::create(['tenant_id' => $one->id, 'key' => 'currency', 'value' => 'usd']);
    Preference::create(['tenant_id' => $two->id, 'key' => 'currency', 'value' => ' eur ']);

    runCurrencyNormalization();

    expect(DB::table('preferences')->where('tenant_id', $one->id)->value('value'))->toBe('USD');
    expect(DB::table('preferences')->where('tenant_id', $two->id)->value('value'))->toBe('EUR');
});

test('it falls back to SDG for blank or non three-letter values', function () {
    $blank = makeTenant('blank');
    $null = makeTenant('null');
    $long = makeTenant('long');
    Preference::create(['tenant_id' => $blank->id, 'key' => 'currency', 'value' => '']);
    Preference::create(['tenant_id' => $null->id, 'key' => 'currency', 'value' => null]);
    Preference::create(['tenant_id' => $long->id, 'key' => 'currency', 'value' => 'DOLLARS']);

    runCurrencyNormalization();

    expect(DB::table('preferences')->where('tenant_id', $blank->id)->value('value'))->toBe('SDG');
    expect(DB::table('preferences')->where('tenant_id', $null->id)->value('value'))->toBe('SDG');
    expect(DB::table('preferences')->where('tenant_id', $long->id)->value('value'))->toBe('SDG');
});

test('it leaves valid three-letter codes untouched', function () {
    $tenant = makeTenant('valid');
    Preference::create(['tenant_id' => $tenant->id, 'key' => 'currency', 'value' => 'SDG']);

    runCurrencyNormalization();

    expect(DB::table('preferences')->where('tenant_id', $tenant->id)->value('value'))->toBe('SDG');
});

test('it ignores preferences other than currency', function () {
    $tenant = makeTenant('other');
    Preference::create(['tenant_id' => $tenant->id, 'key' => 'invoicesHeadline', 'value' => 'my shop']);

    runCurrencyNormalization();

    expect(DB::table('preferences')->where('key', 'invoicesHeadline')->value('value'))->toBe('my shop');
});
