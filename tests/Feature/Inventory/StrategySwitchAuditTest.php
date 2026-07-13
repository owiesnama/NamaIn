<?php

use App\Exceptions\ImmutableRecordException;
use App\Models\Preference;
use App\Models\Product;
use App\Models\Storage;
use App\Models\TenantSettingHistory;
use App\Models\User;

test('changing the inventory strategy records a dated history event', function () {
    Preference::create(['key' => 'inventory_strategy', 'value' => 'purchase_driven']);
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('preferences.update'), [
        'inventory_strategy' => 'free_form',
        'allow_overselling' => true,
    ])->assertRedirect();

    $this->assertDatabaseHas('tenant_settings_history', [
        'key' => 'inventory_strategy',
        'old_value' => 'purchase_driven',
        'new_value' => 'free_form',
        'changed_by' => $user->id,
    ]);
    $this->assertDatabaseHas('tenant_settings_history', [
        'key' => 'allow_overselling',
        'new_value' => '1',
    ]);
});

test('re-saving the same strategy records no new history event', function () {
    Preference::create(['key' => 'inventory_strategy', 'value' => 'free_form']);

    $this->actingAs(User::factory()->create())->post(route('preferences.update'), [
        'inventory_strategy' => 'free_form',
    ])->assertRedirect();

    expect(TenantSettingHistory::where('key', 'inventory_strategy')->count())->toBe(0);
});

test('existing negative balances carry over unchanged when switching to purchase-driven', function () {
    Preference::create(['key' => 'inventory_strategy', 'value' => 'free_form']);
    Preference::create(['key' => 'allow_overselling', 'value' => '1']);
    $storage = Storage::factory()->create();
    $product = Product::factory()->create();
    $storage->addStock($product, 2, 'purchase_receipt');
    $storage->deductStock($product, 5, 'sale_delivery');
    expect($storage->quantityOf($product))->toBe(-3);

    $this->actingAs(User::factory()->create())->post(route('preferences.update'), [
        'inventory_strategy' => 'purchase_driven',
        'allow_overselling' => false,
    ])->assertRedirect();

    // Switching only prevents NEW negatives; the existing one is untouched.
    expect($storage->quantityOf($product))->toBe(-3);
});

test('tenant setting history is append-only', function () {
    $history = TenantSettingHistory::create([
        'key' => 'inventory_strategy',
        'old_value' => 'purchase_driven',
        'new_value' => 'free_form',
        'changed_by' => null,
    ]);

    expect(fn () => $history->update(['new_value' => 'x']))->toThrow(ImmutableRecordException::class);
    expect(fn () => $history->delete())->toThrow(ImmutableRecordException::class);
});
