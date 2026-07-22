<?php

namespace App\Actions\Reconciliation;

use App\Actions\Treasury\RecordTreasuryAdjustmentAction;
use App\Enums\ResolutionKind;
use App\Models\ChangeLog;
use App\Models\ReconciliationItem;
use App\Models\SessionVariance;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Resolves a session-variance item (Design 04 §2.3). Acknowledge accepts the
 * counted amount (the drawer was already balanced at close); AdjustDrawer
 * re-corrects the drawer to the true amount through the existing treasury
 * adjustment primitive when the count itself was wrong.
 *
 * @phpstan-type VarianceParams array{new_balance?: int, note?: string}
 */
class ResolveSessionVarianceAction
{
    public function __construct(private RecordTreasuryAdjustmentAction $recordAdjustment) {}

    /**
     * @param  VarianceParams  $params
     */
    public function handle(ReconciliationItem $item, ResolutionKind $resolution, User $actor, array $params = []): void
    {
        /** @var SessionVariance $subject */
        $subject = $item->subject;

        DB::transaction(function () use ($item, $resolution, $actor, $params, $subject): void {
            ChangeLog::lockTenant($subject->tenant_id);

            if ($resolution === ResolutionKind::AdjustDrawer) {
                if (! isset($params['new_balance'])) {
                    throw new InvalidArgumentException('A new drawer balance is required.');
                }

                $this->recordAdjustment->handle(
                    account: $subject->drawer,
                    newBalance: (int) $params['new_balance'],
                    notes: $params['note'] ?? __('Session variance drawer correction.'),
                    actor: $actor,
                );
            } elseif ($resolution !== ResolutionKind::Acknowledge) {
                throw new InvalidArgumentException('Unsupported session-variance resolution: '.$resolution->value);
            }

            $item->resolveWith($resolution, $actor, $params['note'] ?? null);
        });
    }
}
