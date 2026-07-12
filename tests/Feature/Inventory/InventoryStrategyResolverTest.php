<?php

use App\Enums\InventoryStrategyType;
use App\Models\Preference;
use App\Models\User;
use App\Services\Inventory\InventoryStrategy;
use App\Services\Inventory\InventoryStrategyResolver;

test('existing tenants with no preference resolve to purchase-driven', function () {
    $strategy = app(InventoryStrategyResolver::class)->resolve();

    expect($strategy->type())->toBe(InventoryStrategyType::PurchaseDriven);
    expect($strategy->allowsOverselling())->toBeFalse();
});

test('resolves free-form with overselling on from preferences', function () {
    Preference::create(['key' => 'inventory_strategy', 'value' => 'free_form']);
    Preference::create(['key' => 'allow_overselling', 'value' => '1']);

    $strategy = app(InventoryStrategyResolver::class)->resolve();

    expect($strategy->type())->toBe(InventoryStrategyType::FreeForm);
    expect($strategy->allowsManualStockIncrease())->toBeTrue();
    expect($strategy->allowsOverselling())->toBeTrue();
});

test('free-form with overselling off still guards deductions', function () {
    Preference::create(['key' => 'inventory_strategy', 'value' => 'free_form']);
    Preference::create(['key' => 'allow_overselling', 'value' => '0']);

    $strategy = app(InventoryStrategyResolver::class)->resolve();

    expect($strategy->allowsOverselling())->toBeFalse();
    expect($strategy->permitsDeduction(2, 5))->toBeFalse();
});

test('the container binds InventoryStrategy to the resolved strategy', function () {
    expect(app(InventoryStrategy::class))->toBeInstanceOf(InventoryStrategy::class)
        ->and(app(InventoryStrategy::class)->type())->toBe(InventoryStrategyType::PurchaseDriven);
});

test('the settings form persists the strategy and overselling preferences', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('preferences.update'), [
        'inventory_strategy' => 'free_form',
        'allow_overselling' => true,
    ])->assertRedirect();

    $this->assertDatabaseHas('preferences', ['key' => 'inventory_strategy', 'value' => 'free_form']);
    $this->assertDatabaseHas('preferences', ['key' => 'allow_overselling', 'value' => '1']);
});
