<?php

namespace App\Services\Inventory;

use App\Enums\InventoryStrategyType;

/**
 * Resolves the current tenant's inventory strategy from its preferences.
 *
 * Existing tenants have no preference set, so they resolve to purchase_driven,
 * which matches NamaIn's historical behaviour exactly.
 */
class InventoryStrategyResolver
{
    public function resolve(): InventoryStrategy
    {
        return match ($this->type()) {
            InventoryStrategyType::FreeForm => new FreeFormStrategy($this->allowsOverselling()),
            InventoryStrategyType::PurchaseDriven => new PurchaseDrivenStrategy,
        };
    }

    public function type(): InventoryStrategyType
    {
        return InventoryStrategyType::tryFrom((string) preference('inventory_strategy', InventoryStrategyType::PurchaseDriven->value))
            ?? InventoryStrategyType::PurchaseDriven;
    }

    private function allowsOverselling(): bool
    {
        return filter_var(preference('allow_overselling', false), FILTER_VALIDATE_BOOLEAN);
    }
}
