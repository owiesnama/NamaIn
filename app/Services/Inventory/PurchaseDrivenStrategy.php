<?php

namespace App\Services\Inventory;

use App\Enums\InventoryStrategyType;

/**
 * Strict discipline: positive stock enters only via purchase invoices, and
 * sales are blocked at zero. Manual upward adjustments are not allowed.
 */
class PurchaseDrivenStrategy implements InventoryStrategy
{
    public function type(): InventoryStrategyType
    {
        return InventoryStrategyType::PurchaseDriven;
    }

    public function allowsManualStockIncrease(): bool
    {
        return false;
    }

    public function allowsOverselling(): bool
    {
        return false;
    }

    public function permitsDeduction(int $available, int $requested): bool
    {
        return $available >= $requested;
    }
}
