<?php

use App\Enums\StorageType;
use App\Models\Preference;
use App\Models\Storage;
use App\Models\Tenant;
use App\Models\TreasuryAccount;
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
        'pecentage' => 70,
    ])->assertRedirect();

    $this->assertDatabaseHas('preferences', ['key' => 'currency', 'value' => 'USD']);
    $this->assertDatabaseHas('preferences', ['key' => 'pecentage', 'value' => '70']);
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

test('the settings page exposes treasury accounts and sale points for the POS section', function () {
    $cash = TreasuryAccount::factory()->cash()->create(['name' => 'Front Drawer']);
    $bank = TreasuryAccount::factory()->bank()->create(['name' => 'Main Bank']);
    $salePoint = Storage::factory()->create(['type' => StorageType::SALE_POINT]);

    $this->get(route('preferences.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Preferences/Show')
            ->where('cash_accounts.0.id', $cash->id)
            ->where('bank_accounts.0.id', $bank->id)
            ->where('sale_points.0.id', $salePoint->id)
        );
});

test('it updates POS default account preferences', function () {
    $bank = TreasuryAccount::factory()->bank()->create();
    $salePoint = Storage::factory()->create(['type' => StorageType::SALE_POINT]);

    $this->post(route('preferences.update'), [
        'pos_default_bank_account_id' => $bank->id,
        'pos_default_sale_point_id' => $salePoint->id,
    ])->assertRedirect();

    $this->assertDatabaseHas('preferences', ['key' => 'pos_default_bank_account_id', 'value' => $bank->id]);
    $this->assertDatabaseHas('preferences', ['key' => 'pos_default_sale_point_id', 'value' => $salePoint->id]);
});

test('it rejects a bank preference pointing at a non-bank account', function () {
    $cash = TreasuryAccount::factory()->cash()->create();

    $this->post(route('preferences.update'), [
        'pos_default_bank_account_id' => $cash->id,
    ])->assertSessionHasErrors('pos_default_bank_account_id');

    $this->assertDatabaseMissing('preferences', ['key' => 'pos_default_bank_account_id']);
});

test('it rejects a POS account preference from another tenant', function () {
    $otherTenant = Tenant::create(['name' => 'Other', 'slug' => 'other', 'is_active' => true]);
    $foreignBank = TreasuryAccount::factory()->bank()->create(['tenant_id' => $otherTenant->id]);

    $this->post(route('preferences.update'), [
        'pos_default_bank_account_id' => $foreignBank->id,
    ])->assertSessionHasErrors('pos_default_bank_account_id');
});

test('the settings page exposes the logo as a url, not the raw disk path', function () {
    // The sidebar's <ApplicationLogo> reads preferences('logo') straight into an
    // <img src>. HandleInertiaRequests resolves the stored disk path to a URL, but
    // a page prop named 'preferences' would override that shared prop and 404 the
    // logo on this page only.
    Preference::create(['key' => 'logo', 'value' => 'logos/acme.png']);

    $this->get(route('preferences.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Preferences/Show')
            ->where('preferences.logo', asset('storage/logos/acme.png'))
        );
});
