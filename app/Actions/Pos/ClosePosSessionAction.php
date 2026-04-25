<?php

namespace App\Actions\Pos;

use App\Actions\Treasury\RecordTreasuryAdjustmentAction;
use App\Enums\TreasuryAccountType;
use App\Models\PosSession;
use App\Models\TreasuryAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ClosePosSessionAction
{
    public function __construct(
        private RecordTreasuryAdjustmentAction $recordAdjustment,
    ) {}

    public function execute(PosSession $session, int $closingFloat, User $actor): void
    {
        if (! $session->isOpen()) {
            throw new \DomainException('POS session is already closed.');
        }

        DB::transaction(function () use ($session, $closingFloat, $actor) {
            $session->update([
                'closed_by' => $actor->id,
                'closing_float' => $closingFloat,
                'closed_at' => now(),
            ]);

            $session->storage->update([
                'active_session_id' => null,
            ]);

            $cashDrawer = TreasuryAccount::where('sale_point_id', $session->storage_id)
                ->ofType(TreasuryAccountType::Cash)
                ->first();

            if ($cashDrawer) {
                $expected = $cashDrawer->currentBalance();

                if ($expected !== $closingFloat) {
                    $this->recordAdjustment->handle(
                        account: $cashDrawer,
                        newBalance: $closingFloat,
                        notes: "POS session #{$session->id} closing reconciliation. Expected: {$expected}, Counted: {$closingFloat}",
                        actor: $actor,
                    );
                }
            }
        });
    }
}
