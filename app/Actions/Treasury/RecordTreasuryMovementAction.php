<?php

namespace App\Actions\Treasury;

use App\Enums\TreasuryMovementReason;
use App\Models\TreasuryAccount;
use App\Models\TreasuryMovement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RecordTreasuryMovementAction
{
    public function handle(
        TreasuryAccount $account,
        int $amount,
        TreasuryMovementReason $reason,
        Model $movable,
        User $actor,
        ?string $notes = null,
        ?Carbon $occurredAt = null,
    ): TreasuryMovement {
        return DB::transaction(function () use ($account, $amount, $reason, $movable, $actor, $notes, $occurredAt) {
            $account = TreasuryAccount::lockForUpdate()->findOrFail($account->id);

            $balanceAfter = $account->currentBalance() + $amount;

            return TreasuryMovement::create([
                'treasury_account_id' => $account->id,
                'created_by' => $actor->id,
                'movable_type' => get_class($movable),
                'movable_id' => $movable->id,
                'reason' => $reason,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'notes' => $notes,
                'occurred_at' => $occurredAt ?? now(),
            ]);
        });
    }
}
