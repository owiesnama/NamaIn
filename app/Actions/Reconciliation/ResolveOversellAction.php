<?php

namespace App\Actions\Reconciliation;

use App\Actions\Stock\RecordAdjustmentAction;
use App\Actions\Stock\TransferStockAction;
use App\Enums\ResolutionKind;
use App\Models\ChangeLog;
use App\Models\OversellReconciliation;
use App\Models\ReconciliationItem;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Resolves an oversell item (Design 04 §2.1, R4) by dispatching to the existing
 * ledgered stock primitives — never touching `stocks` directly — then flipping
 * the inbox item to resolved in the same transaction.
 *
 * @phpstan-type OversellParams array{counted_qty?: int, from_storage_id?: int, quantity?: int, note?: string}
 */
class ResolveOversellAction
{
    public function __construct(
        private RecordAdjustmentAction $recordAdjustment,
        private TransferStockAction $transferStock,
    ) {}

    /**
     * @param  OversellParams  $params
     */
    public function handle(ReconciliationItem $item, ResolutionKind $resolution, User $actor, array $params = []): void
    {
        /** @var OversellReconciliation $subject */
        $subject = $item->subject;

        DB::transaction(function () use ($item, $resolution, $actor, $params, $subject): void {
            ChangeLog::lockTenant($subject->tenant_id);

            match ($resolution) {
                ResolutionKind::Adjust => $this->recordAdjustment->handle(
                    $subject->storage, $subject->product, (int) ($params['counted_qty'] ?? 0), 'adjustment', $actor, $params['note'] ?? null, reconciling: true,
                ),
                ResolutionKind::Shrinkage => $this->recordAdjustment->handle(
                    $subject->storage, $subject->product, (int) ($params['counted_qty'] ?? 0), 'shrinkage', $actor, $params['note'] ?? null, reconciling: true,
                ),
                ResolutionKind::Transfer => $this->transfer($subject, $actor, $params),
                default => throw new InvalidArgumentException('Unsupported oversell resolution: '.$resolution->value),
            };

            $item->resolveWith($resolution, $actor, $params['note'] ?? null);
        });
    }

    /**
     * @param  OversellParams  $params
     */
    private function transfer(OversellReconciliation $subject, User $actor, array $params): void
    {
        if (empty($params['from_storage_id'])) {
            throw new InvalidArgumentException('A source storage is required to cover the shortfall by transfer.');
        }

        $transfer = StockTransfer::create([
            'tenant_id' => $subject->tenant_id,
            'from_storage_id' => (int) $params['from_storage_id'],
            'to_storage_id' => $subject->storage_id,
            'created_by' => $actor->id,
            'notes' => $params['note'] ?? null,
        ]);

        $transfer->lines()->create([
            'tenant_id' => $subject->tenant_id,
            'product_id' => $subject->product_id,
            'quantity' => (int) ($params['quantity'] ?? $subject->oversold_qty),
        ]);

        $this->transferStock->handle($transfer, $actor);
    }
}
