<?php

namespace App\Services\Inventory;

use App\Enums\InventoryStrategyType;

/**
 * A tenant's inventory strategy: the single place that answers "how does this
 * tenant want to manage stock?". Strategy changes only which validation rules
 * and entry paths are permitted — the ledger schema is identical in every mode.
 */
interface InventoryStrategy
{
    public function type(): InventoryStrategyType;

    /**
     * May stock be raised manually (adjustments / product-edit) without a
     * purchase invoice? False under purchase_driven.
     */
    public function allowsManualStockIncrease(): bool;

    /**
     * May a sale drive the balance negative? Only free_form with the nested
     * allow_overselling sub-setting turned on.
     */
    public function allowsOverselling(): bool;

    /**
     * Whether a deduction of $requested is permitted given $available on hand.
     * The caller owns the InsufficientStockException (it holds product/storage).
     */
    public function permitsDeduction(int $available, int $requested): bool;
}
