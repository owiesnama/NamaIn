<?php

namespace App\Actions;

use App\Actions\Treasury\RecordTreasuryMovementAction;
use App\Enums\ChequeStatus;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Enums\TreasuryAccountType;
use App\Enums\TreasuryMovementReason;
use App\Models\Cheque;
use App\Models\TreasuryAccount;
use Illuminate\Support\Facades\DB;

class ClearCheque
{
    public function __construct(
        private RecordPaymentAction $recordPayment,
        private RecordTreasuryMovementAction $recordMovement,
    ) {}

    public function handle(Cheque $cheque, ?float $clearedAmount = null, ?int $treasuryAccountId = null): Cheque
    {
        return DB::transaction(function () use ($cheque, $clearedAmount, $treasuryAccountId) {
            $amountToRecord = $clearedAmount ?? ($cheque->amount - $cheque->cleared_amount);
            $amountInCents = (int) round($amountToRecord * 100);

            $totalClearedSoFar = $cheque->cleared_amount + $amountToRecord;
            $newStatus = $totalClearedSoFar < $cheque->amount
                ? ChequeStatus::PartiallyCleared
                : ChequeStatus::Cleared;

            $directionLabel = $cheque->isReceivable()
                ? "Incoming payment from {$cheque->payee->name}"
                : "Outgoing payment to {$cheque->payee->name}";

            $payment = $this->recordPayment->handle(
                invoice: $cheque->invoice_id ? $cheque->invoice : null,
                payable: $cheque->payee,
                amount: $amountToRecord,
                method: PaymentMethod::Cheque,
                direction: $cheque->isReceivable() ? PaymentDirection::In : PaymentDirection::Out,
                options: [
                    'reference' => $cheque->reference_number,
                    'notes' => "Cheque clearance — {$cheque->bank}. {$directionLabel}",
                    'paid_at' => now()->toDateTimeString(),
                ]
                // No cheque_due → RecordPaymentAction will not create a new cheque
            );

            if ($cheque->isReceivable()) {
                // 1. Debit the cheque_clearing pool (cheque exits the "in hand" state)
                $clearingAccount = TreasuryAccount::ofType(TreasuryAccountType::ChequeClearing)->active()->first();

                if ($clearingAccount) {
                    $this->recordMovement->handle(
                        account: $clearingAccount,
                        amount: -$amountInCents,
                        reason: TreasuryMovementReason::ChequeDeposited,
                        movable: $payment,
                        actor: auth()->user(),
                    );
                }

                // 2. Credit the bank account (money lands in the bank)
                $bankAccount = $this->resolveBankAccount($cheque, $treasuryAccountId);
                $this->recordMovement->handle(
                    account: $bankAccount,
                    amount: $amountInCents,
                    reason: TreasuryMovementReason::ChequeReceived,
                    movable: $payment,
                    actor: auth()->user(),
                );
            } elseif ($cheque->isPayable()) {
                // Debit the bank account (money leaves the bank when the cheque clears)
                $bankAccount = $this->resolveBankAccount($cheque, $treasuryAccountId);
                $this->recordMovement->handle(
                    account: $bankAccount,
                    amount: -$amountInCents,
                    reason: TreasuryMovementReason::ChequeCleared,
                    movable: $payment,
                    actor: auth()->user(),
                );
            }

            $cheque->update([
                'cleared_amount' => $totalClearedSoFar,
                'status' => $newStatus,
            ]);

            return $cheque;
        });
    }

    private function resolveBankAccount(Cheque $cheque, ?int $treasuryAccountId): TreasuryAccount
    {
        // Try the bank linked to this cheque's bank institution first
        if ($cheque->bank_id) {
            $linked = TreasuryAccount::where('bank_id', $cheque->bank_id)->active()->first();
            if ($linked) {
                return $linked;
            }
        }

        // Fall back to the manually chosen treasury account
        if ($treasuryAccountId) {
            $account = TreasuryAccount::active()->find($treasuryAccountId);
            if ($account) {
                return $account;
            }
        }

        throw new \RuntimeException(
            "Cannot clear cheque #{$cheque->id}: no bank treasury account is linked to bank '{$cheque->bank}'. ".
            'Link a treasury account to this bank or select one manually.'
        );
    }
}
