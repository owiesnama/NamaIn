<?php

namespace App\Actions\Treasury;

use App\Enums\TreasuryMovementReason;
use App\Models\TreasuryAccount;
use App\Models\TreasuryMovement;
use App\Models\User;

class RecordTreasuryAdjustmentAction
{
    public function __construct(
        private RecordTreasuryMovementAction $recordMovement,
    ) {}

    public function handle(
        TreasuryAccount $account,
        int $newBalance,
        string $notes,
        User $actor,
    ): TreasuryMovement {
        $delta = $newBalance - $account->currentBalance();

        return $this->recordMovement->handle(
            account: $account,
            amount: $delta,
            reason: TreasuryMovementReason::ManualAdjustment,
            movable: $account,
            actor: $actor,
            notes: $notes,
        );
    }
}
