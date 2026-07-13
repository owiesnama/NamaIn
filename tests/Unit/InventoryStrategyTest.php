<?php

use App\Enums\InventoryStrategyType;
use App\Services\Inventory\FreeFormStrategy;
use App\Services\Inventory\PurchaseDrivenStrategy;

test('purchase-driven blocks manual increases and overselling', function () {
    $strategy = new PurchaseDrivenStrategy;

    expect($strategy->type())->toBe(InventoryStrategyType::PurchaseDriven);
    expect($strategy->allowsManualStockIncrease())->toBeFalse();
    expect($strategy->allowsOverselling())->toBeFalse();
    expect($strategy->permitsDeduction(10, 5))->toBeTrue();
    expect($strategy->permitsDeduction(5, 10))->toBeFalse();
});

test('free-form allows manual increases; overselling follows the sub-setting', function () {
    $off = new FreeFormStrategy(allowOverselling: false);

    expect($off->type())->toBe(InventoryStrategyType::FreeForm);
    expect($off->allowsManualStockIncrease())->toBeTrue();
    expect($off->allowsOverselling())->toBeFalse();
    expect($off->permitsDeduction(5, 10))->toBeFalse(); // still guards at zero

    $on = new FreeFormStrategy(allowOverselling: true);

    expect($on->allowsOverselling())->toBeTrue();
    expect($on->permitsDeduction(5, 10))->toBeTrue(); // oversell permitted
    expect($on->permitsDeduction(0, 3))->toBeTrue();
});
