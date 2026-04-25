<?php

namespace App\Actions\Treasury;

use App\Enums\TreasuryMovementReason;
use App\Models\TreasuryAccount;
use App\Models\TreasuryTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ExecuteTreasuryTransferAction
{
    public function __construct(
        private RecordTreasuryMovementAction $recordMovement,
    ) {}

    public function handle(
        TreasuryAccount $from,
        TreasuryAccount $to,
        int $amount,
        User $actor,
        ?string $notes = null,
    ): TreasuryTransfer {
        if ($from->id === $to->id) {
            throw new InvalidArgumentException('Source and destination accounts cannot be the same.');
        }

        return DB::transaction(function () use ($from, $to, $amount, $actor, $notes) {
            $transfer = TreasuryTransfer::create([
                'from_account_id' => $from->id,
                'to_account_id' => $to->id,
                'created_by' => $actor->id,
                'amount' => $amount,
                'notes' => $notes,
                'transferred_at' => now(),
            ]);

            $this->recordMovement->handle($from, -$amount, TreasuryMovementReason::TransferOut, $transfer, $actor, $notes);
            $this->recordMovement->handle($to, $amount, TreasuryMovementReason::TransferIn, $transfer, $actor, $notes);

            return $transfer;
        });
    }
}
