<?php

namespace App\Enums;

use App\Models\CreditBreachFlag;
use App\Models\OversellReconciliation;
use App\Models\ParkedMutation;
use App\Models\SessionVariance;

/**
 * The four kinds of divergence the reconciliation inbox surfaces (Design 04
 * §1.1). Each maps to a concrete subject table — two upstream (oversell, credit
 * breach) and two introduced here (session variance, parked mutation). The
 * denormalized `type` column on `reconciliation_items` carries this value so the
 * inbox lists and filters without touching each subject table.
 */
enum ReconciliationType: string
{
    case Oversell = 'oversell';
    case CreditBreach = 'credit_breach';
    case SessionVariance = 'session_variance';
    case ParkedMutation = 'parked_mutation';

    /** @return class-string */
    public function subjectClass(): string
    {
        return match ($this) {
            self::Oversell => OversellReconciliation::class,
            self::CreditBreach => CreditBreachFlag::class,
            self::SessionVariance => SessionVariance::class,
            self::ParkedMutation => ParkedMutation::class,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Oversell => __('Oversell'),
            self::CreditBreach => __('Credit breach'),
            self::SessionVariance => __('Session variance'),
            self::ParkedMutation => __('Parked mutation'),
        };
    }

    /**
     * The resolution kinds an owner may pick for this item type (Design 04 §2).
     *
     * @return list<ResolutionKind>
     */
    public function resolutions(): array
    {
        return match ($this) {
            self::Oversell => [ResolutionKind::Adjust, ResolutionKind::Transfer, ResolutionKind::Shrinkage],
            self::CreditBreach => [ResolutionKind::Acknowledge, ResolutionKind::Collect, ResolutionKind::RaiseLimit],
            self::SessionVariance => [ResolutionKind::Acknowledge, ResolutionKind::AdjustDrawer],
            self::ParkedMutation => [ResolutionKind::Acknowledge],
        };
    }
}
