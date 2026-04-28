<?php

namespace App\Actions;

use App\Actions\Treasury\RecordTreasuryMovementAction;
use App\Enums\ChequeType;
use App\Enums\TreasuryAccountType;
use App\Enums\TreasuryMovementReason;
use App\Exceptions\ChequeClearingAccountRequired;
use App\Models\Cheque;
use App\Models\TreasuryAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterChequeAction
{
    public function __construct(
        private ResolveChequeBankAction $resolveChequeBank,
        private RecordTreasuryMovementAction $recordMovement,
    ) {}

    public function handle(array $attributes, User $actor): Cheque
    {
        $clearingAccount = $this->requiredClearingAccount($attributes);

        return DB::transaction(function () use ($attributes, $actor, $clearingAccount) {
            $cheque = Cheque::forPayee($attributes['payee_id'], $attributes['payee_type'])
                ->register([
                    'type' => $attributes['type'],
                    'amount' => $attributes['amount'],
                    'bank_id' => $this->resolveChequeBank->handle($attributes['bank_id']),
                    'due' => $attributes['due'],
                    'reference_number' => $attributes['reference_number'],
                    'invoice_id' => $attributes['invoice_id'] ?? null,
                    'notes' => $attributes['notes'] ?? null,
                ]);

            if ($clearingAccount) {
                $this->recordMovement->handle(
                    account: $clearingAccount,
                    amount: (int) round($attributes['amount'] * 100),
                    reason: TreasuryMovementReason::ChequeDeposited,
                    movable: $cheque,
                    actor: $actor,
                );
            }

            return $cheque;
        });
    }

    private function requiredClearingAccount(array $attributes): ?TreasuryAccount
    {
        if (ChequeType::from((int) $attributes['type']) !== ChequeType::Receivable) {
            return null;
        }

        $clearingAccount = TreasuryAccount::ofType(TreasuryAccountType::ChequeClearing)
            ->active()
            ->first();

        if (! $clearingAccount) {
            throw new ChequeClearingAccountRequired;
        }

        return $clearingAccount;
    }
}
