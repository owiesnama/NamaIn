<?php

namespace App\Actions;

use App\Enums\ChequeType;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Model;

class RecordPaymentAction
{
    public function __construct(private CreateChequeAction $createCheque) {}

    /**
     * Record a payment against an invoice or directly against a payable party.
     *
     * Pass cheque-specific keys in $options to create a cheque alongside the payment:
     *   cheque_due, cheque_bank_id, cheque_reference
     *
     * @param  array{reference?: string, notes?: string, metadata?: array, receipt_path?: string, paid_at?: string, cheque_due?: string, cheque_bank_id?: int|string|null, cheque_reference?: string|null}  $options
     */
    public function handle(
        ?Invoice $invoice,
        Model $payable,
        float $amount,
        PaymentMethod $method,
        array $options = []
    ): Payment {
        $payment = $invoice
            ? $this->recordForInvoice($invoice, $amount, $method, $options)
            : $this->recordStandalone($payable, $amount, $method, $options);

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

        return $payment;
    }

    private function recordForInvoice(Invoice $invoice, float $amount, PaymentMethod $method, array $options): Payment
    {
        return $invoice->recordPayment(
            amount: $amount,
            method: $method,
            reference: $options['reference'] ?? null,
            notes: $options['notes'] ?? null,
            metadata: $options['metadata'] ?? null,
            receiptPath: $options['receipt_path'] ?? null,
            paidAt: $options['paid_at'] ?? null,
        );
    }

    private function recordStandalone(Model $payable, float $amount, PaymentMethod $method, array $options): Payment
    {
        return Payment::create([
            'payable_id' => $payable->id,
            'payable_type' => get_class($payable),
            'amount' => $amount,
            'payment_method' => $method,
            'reference' => $options['reference'] ?? null,
            'notes' => $options['notes'] ?? null,
            'paid_at' => $options['paid_at'] ?? now(),
            'created_by' => auth()->id(),
            'metadata' => $options['metadata'] ?? null,
            'receipt_path' => $options['receipt_path'] ?? null,
        ]);
    }
}
