<?php

namespace App\Enums;

enum MovementType: string
{
    case OpeningBalance = 'opening_balance';
    case PurchaseReceipt = 'purchase_receipt';
    case InvoiceAddition = 'invoice_addition';
    case InvoiceDeduction = 'invoice_deduction';
    case SaleDelivery = 'sale_delivery';
    case Adjustment = 'adjustment';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case SalesReturn = 'sales_return';
    case PurchaseReturn = 'purchase_return';

    /**
     * Resolve a movement type from a free-form `reason` string.
     *
     * The nine runtime reasons emitted by Storage's write paths are identical
     * to this enum's backing values, so they resolve exactly. Unknown/legacy
     * reasons (older data, ad-hoc strings) fall back to Adjustment rather than
     * throwing, so the backfill migration never fails on surprise data.
     */
    public static function fromReason(string $reason): self
    {
        return self::tryFrom($reason) ?? self::Adjustment;
    }

    /**
     * Whether this movement type increases stock on hand.
     */
    public function isIncrease(): bool
    {
        return match ($this) {
            self::OpeningBalance,
            self::PurchaseReceipt,
            self::InvoiceAddition,
            self::TransferIn,
            self::SalesReturn => true,
            self::InvoiceDeduction,
            self::SaleDelivery,
            self::TransferOut,
            self::PurchaseReturn => false,
            self::Adjustment => false,
        };
    }

    /**
     * Human-readable label (localize at the call site via __()).
     */
    public function label(): string
    {
        return match ($this) {
            self::OpeningBalance => 'Opening Balance',
            self::PurchaseReceipt => 'Purchase Receipt',
            self::InvoiceAddition => 'Invoice Addition',
            self::InvoiceDeduction => 'Invoice Deduction',
            self::SaleDelivery => 'Sale Delivery',
            self::Adjustment => 'Adjustment',
            self::TransferIn => 'Transfer In',
            self::TransferOut => 'Transfer Out',
            self::SalesReturn => 'Sales Return',
            self::PurchaseReturn => 'Purchase Return',
        };
    }
}
