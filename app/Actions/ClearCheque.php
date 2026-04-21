<?php

namespace App\Actions;

use App\Enums\ChequeStatus;
use App\Enums\PaymentMethod;
use App\Models\Cheque;
use Illuminate\Support\Facades\DB;

class ClearCheque
{
    public function __construct(private RecordPaymentAction $recordPayment) {}

    public function handle(Cheque $cheque, ?float $clearedAmount = null): Cheque
    {
        return DB::transaction(function () use ($cheque, $clearedAmount) {
            $amountToRecord = $clearedAmount ?? ($cheque->amount - $cheque->cleared_amount);

            $totalClearedSoFar = $cheque->cleared_amount + $amountToRecord;
            $newStatus = $totalClearedSoFar < $cheque->amount
                ? ChequeStatus::PartiallyCleared
                : ChequeStatus::Cleared;

            $directionLabel = $cheque->isReceivable()
                ? "Incoming payment from {$cheque->payee->name}"
                : "Outgoing payment to {$cheque->payee->name}";

            $this->recordPayment->handle(
                invoice: $cheque->invoice_id ? $cheque->invoice : null,
                payable: $cheque->payee,
                amount: $amountToRecord,
                method: PaymentMethod::Cheque,
                options: [
                    'reference' => $cheque->reference_number,
                    'notes' => "Cheque clearance — {$cheque->bank}. {$directionLabel}",
                    'paid_at' => now()->toDateTimeString(),
                ]
                // No cheque_due → RecordPaymentAction will not create a new cheque
            );

            $cheque->update([
                'cleared_amount' => $totalClearedSoFar,
                'status' => $newStatus,
            ]);

            return $cheque;
        });
    }
}
