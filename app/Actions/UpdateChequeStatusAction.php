<?php

namespace App\Actions;

use App\Actions\Treasury\RecordTreasuryMovementAction;
use App\Enums\ChequeStatus;
use App\Enums\TreasuryAccountType;
use App\Enums\TreasuryMovementReason;
use App\Models\Cheque;
use App\Models\TreasuryAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateChequeStatusAction
{
    public function __construct(
        private ClearCheque $clearCheque,
        private RecordTreasuryMovementAction $recordMovement,
    ) {}

    public function handle(
        Cheque $cheque,
        ChequeStatus $status,
        ?float $clearedAmount,
        ?int $treasuryAccountId,
        User $actor,
    ): Cheque {
        if ($this->isClearingStatus($status)) {
            return $this->clearCheque->handle($cheque, $clearedAmount, $treasuryAccountId);
        }

        return DB::transaction(function () use ($cheque, $status, $actor) {
            if ($this->shouldReverseReceivableCheque($cheque, $status)) {
                $this->reverseChequeClearingBalance($cheque, $actor);
            }

            $cheque->update(['status' => $status]);

            return $cheque;
        });
    }

    private function isClearingStatus(ChequeStatus $status): bool
    {
        return $status === ChequeStatus::Cleared || $status === ChequeStatus::PartiallyCleared;
    }

    private function shouldReverseReceivableCheque(Cheque $cheque, ChequeStatus $status): bool
    {
        return $status === ChequeStatus::Returned && $cheque->isReceivable();
    }

    private function reverseChequeClearingBalance(Cheque $cheque, User $actor): void
    {
        $clearingAccount = TreasuryAccount::ofType(TreasuryAccountType::ChequeClearing)
            ->active()
            ->first();

        if (! $clearingAccount) {
            return;
        }

        $this->recordMovement->handle(
            account: $clearingAccount,
            amount: -(int) round($cheque->amount * 100),
            reason: TreasuryMovementReason::ChequeBounced,
            movable: $cheque,
            actor: $actor,
        );
    }
}
