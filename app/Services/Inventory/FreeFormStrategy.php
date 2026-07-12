<?php

namespace App\Services\Inventory;

use App\Enums\InventoryStrategyType;

/**
 * Flexible management: stock can be adjusted freely without a purchase invoice.
 * The nested allow_overselling sub-setting decides whether sales may drive the
 * balance negative (on) or still block at zero (off).
 */
class FreeFormStrategy implements InventoryStrategy
{
    public function __construct(private bool $allowOverselling) {}

    public function type(): InventoryStrategyType
    {
        return InventoryStrategyType::FreeForm;
    }

    public function allowsManualStockIncrease(): bool
    {
        return true;
    }

    public function allowsOverselling(): bool
    {
        return $this->allowOverselling;
    }

    public function permitsDeduction(int $available, int $requested): bool
    {
        return $this->allowOverselling || $available >= $requested;
    }
}
