<?php

namespace App\Actions;

use App\Enums\ChequeStatus;
use App\Enums\PaymentMethod;
use App\Models\Cheque;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class ClearCheque
{
    public function execute(Cheque $cheque, ?float $clearedAmount = null): Cheque
    {
        return DB::transaction(function () use ($cheque, $clearedAmount) {
            // 1. Determine the amount to record
            $amountToRecord = $clearedAmount ?? ($cheque->amount - $cheque->cleared_amount);

            // 2. Determine the new status
            $totalClearedSoFar = $cheque->cleared_amount + $amountToRecord;
            $newStatus = $totalClearedSoFar < $cheque->amount ? ChequeStatus::PartiallyCleared : ChequeStatus::Cleared;

            // 3. Record the financial record
            if ($cheque->invoice_id) {
                // Linked to invoice
                $cheque->invoice->recordPayment(
                    amount: $amountToRecord,
                    method: PaymentMethod::Cheque,
                    reference: $cheque->reference_number,
                    notes: "Cheque clearance — {$cheque->bank}",
                    paidAt: now()->toDateTimeString()
                );
            } else {
                // No invoice_id - direct payment to Customer/Supplier
                $directionLabel = $cheque->isReceivable()
                    ? "Incoming payment from {$cheque->payee->name}"
                    : "Outgoing payment to {$cheque->payee->name}";

                Payment::create([
                    'payable_id' => $cheque->chequeable_id,
                    'payable_type' => $cheque->chequeable_type,
                    'amount' => $amountToRecord,
                    'payment_method' => PaymentMethod::Cheque,
                    'reference' => $cheque->reference_number,
                    'notes' => "Cheque clearance — {$cheque->bank}. {$directionLabel}",
                    'paid_at' => now(),
                    'created_by' => auth()->id(),
                ]);
            }

            // 6. Update cheque
            $cheque->update([
                'cleared_amount' => $totalClearedSoFar,
                'status' => $newStatus,
            ]);

            return $cheque;
        });
    }
}
