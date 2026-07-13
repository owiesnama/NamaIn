<?php

namespace App\Enums;

enum InventoryStrategyType: string
{
    case PurchaseDriven = 'purchase_driven';
    case FreeForm = 'free_form';

    /**
     * Human-readable label (localize at the call site via __()).
     */
    public function label(): string
    {
        return match ($this) {
            self::PurchaseDriven => 'Purchase-driven',
            self::FreeForm => 'Free-form',
        };
    }
}
