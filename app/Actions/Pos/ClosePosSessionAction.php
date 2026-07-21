<?php

namespace App\Actions\Pos;

use App\Actions\Treasury\RecordTreasuryAdjustmentAction;
use App\Enums\TreasuryAccountType;
use App\Models\PosSession;
use App\Models\TreasuryAccount;
use App\Models\User;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\DB;

class ClosePosSessionAction
{
    public function __construct(
        private RecordTreasuryAdjustmentAction $recordAdjustment,
    ) {}

    public function handle(PosSession $session, int $closingFloat, User $actor): void
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
                        notes: __('POS session #:id closing reconciliation. Expected: :expected, Counted: :counted', [
                            'id' => $session->id,
                            'expected' => number_format(Money::fromMinor($expected)->major(), 2),
                            'counted' => number_format(Money::fromMinor($closingFloat)->major(), 2),
                        ]),
                        actor: $actor,
                    );
                }
            }
        });
    }
}
