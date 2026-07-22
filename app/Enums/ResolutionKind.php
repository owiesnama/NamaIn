<?php

namespace App\Enums;

/**
 * How a reconciliation item was resolved (Design 04 §2, R3). A typed record of
 * the human decision — each value wraps an existing domain primitive (adjust,
 * transfer, collect, …) or is a pure acknowledgement with no financial effect.
 * The per-type allowed set lives on {@see ReconciliationType::resolutions()}.
 */
enum ResolutionKind: string
{
    case Acknowledge = 'acknowledge';
    case Adjust = 'adjust';
    case Transfer = 'transfer';
    case Shrinkage = 'shrinkage';
    case Collect = 'collect';
    case RaiseLimit = 'raise_limit';
    case AdjustDrawer = 'adjust_drawer';

    public function label(): string
    {
        return match ($this) {
            self::Acknowledge => __('Acknowledge'),
            self::Adjust => __('Adjust stock'),
            self::Transfer => __('Transfer stock'),
            self::Shrinkage => __('Write off as shrinkage'),
            self::Collect => __('Collect payment'),
            self::RaiseLimit => __('Raise credit limit'),
            self::AdjustDrawer => __('Adjust drawer'),
        };
    }
}
