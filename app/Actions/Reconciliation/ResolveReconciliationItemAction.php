<?php

namespace App\Actions\Reconciliation;

use App\Enums\ReconciliationType;
use App\Enums\ResolutionKind;
use App\Models\ReconciliationItem;
use App\Models\User;
use InvalidArgumentException;

/**
 * Coordinates reconciliation resolution (Design 04 §2): validates the chosen
 * resolution is allowed for the item's type, then dispatches to the type-specific
 * action that wraps the existing primitive. Parked mutations are acknowledge-only.
 */
class ResolveReconciliationItemAction
{
    public function __construct(
        private ResolveOversellAction $resolveOversell,
        private ResolveCreditBreachAction $resolveCreditBreach,
        private ResolveSessionVarianceAction $resolveSessionVariance,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function handle(ReconciliationItem $item, ResolutionKind $resolution, User $actor, array $params = []): void
    {
        if (! in_array($resolution, $item->type->resolutions(), true)) {
            throw new InvalidArgumentException(
                "Resolution {$resolution->value} is not allowed for a {$item->type->value} item.",
            );
        }

        match ($item->type) {
            ReconciliationType::Oversell => $this->resolveOversell->handle($item, $resolution, $actor, $params),
            ReconciliationType::CreditBreach => $this->resolveCreditBreach->handle($item, $resolution, $actor, $params),
            ReconciliationType::SessionVariance => $this->resolveSessionVariance->handle($item, $resolution, $actor, $params),
            ReconciliationType::ParkedMutation => $item->resolveWith($resolution, $actor, $params['note'] ?? null),
        };
    }
}
