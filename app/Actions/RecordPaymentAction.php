<?php

namespace App\Actions;

use App\Actions\Treasury\RecordTreasuryMovementAction;
use App\Enums\ChequeType;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Enums\TreasuryMovementReason;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\TreasuryAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RecordPaymentAction
{
    public function __construct(
        private CreateChequeAction $createCheque,
        private RecordTreasuryMovementAction $recordMovement,
    ) {}

    /**
     * Record a payment against an invoice or directly against a payable party.
     *
     * Pass cheque-specific keys in $options to create a cheque alongside the payment:
     *   cheque_due, cheque_bank_id, cheque_reference
     *
     * Pass treasury_account_id to link the payment to a treasury account and record a movement.
     *
     * @param  array{reference?: string, notes?: string, metadata?: array, receipt_path?: string, paid_at?: string, cheque_due?: string, cheque_bank_id?: int|string|null, cheque_reference?: string|null, treasury_account_id?: int|null, movement_reason?: TreasuryMovementReason}  $options
     */
    public function handle(
        ?Invoice $invoice,
        Model $payable,
        float $amount,
        PaymentMethod $method,
        PaymentDirection $direction,
        array $options = []
    ): Payment {
        return DB::transaction(function () use ($invoice, $payable, $amount, $method, $direction, $options) {
            $payment = $invoice
                ? $this->recordForInvoice($invoice, $amount, $method, $direction, $options)
                : $this->recordStandalone($payable, $amount, $method, $direction, $options);

            if ($method === PaymentMethod::Cheque && isset($options['cheque_due'])) {
                $this->createCheque->handle($payable, [
                    'amount' => $amount,
                    'type' => $payable instanceof Supplier ? ChequeType::Payable : ChequeType::Receivable,
                    'due' => $options['cheque_due'],
                    'bank_id' => $options['cheque_bank_id'] ?? null,
                    'reference_number' => $options['cheque_reference'] ?? null,
                    'invoice_id' => $invoice?->id,
                ]);
            }

            if (isset($options['treasury_account_id'])) {
                $account = TreasuryAccount::findOrFail($options['treasury_account_id']);
                $amountInCents = (int) round($amount * 100);

                $reason = $options['movement_reason']
                    ?? ($direction === PaymentDirection::In
                        ? TreasuryMovementReason::PaymentReceived
                        : TreasuryMovementReason::ExpensePaid);

                $this->recordMovement->handle(
                    account: $account,
                    amount: $direction === PaymentDirection::In ? $amountInCents : -$amountInCents,
                    reason: $reason,
                    movable: $payment,
                    actor: auth()->user(),
                );

                $payment->update(['treasury_account_id' => $account->id]);
            }

            return $payment;
        });
    }

    private function recordForInvoice(Invoice $invoice, float $amount, PaymentMethod $method, PaymentDirection $direction, array $options): Payment
    {
        return $invoice->recordPayment(
            amount: $amount,
            method: $method,
            reference: $options['reference'] ?? null,
            notes: $options['notes'] ?? null,
            metadata: $options['metadata'] ?? null,
            receiptPath: $options['receipt_path'] ?? null,
            paidAt: $options['paid_at'] ?? null,
            direction: $direction,
        );
    }

    private function recordStandalone(Model $payable, float $amount, PaymentMethod $method, PaymentDirection $direction, array $options): Payment
    {
        return Payment::create([
            'payable_id' => $payable->id,
            'payable_type' => get_class($payable),
            'amount' => $amount,
            'payment_method' => $method,
            'direction' => $direction,
            'reference' => $options['reference'] ?? null,
            'notes' => $options['notes'] ?? null,
            'paid_at' => $options['paid_at'] ?? now(),
            'created_by' => auth()->id(),
            'metadata' => $options['metadata'] ?? null,
            'receipt_path' => $options['receipt_path'] ?? null,
        ]);
    }
}
