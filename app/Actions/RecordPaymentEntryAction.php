<?php

namespace App\Actions;

use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Supplier;
use App\Traits\HandlesAsyncUploads;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class RecordPaymentEntryAction
{
    use HandlesAsyncUploads;

    private const PAYABLE_TYPES = [
        Customer::class,
        Supplier::class,
    ];

    public function __construct(
        private RecordPaymentAction $recordPayment,
    ) {}

    public function handle(array $attributes): Payment
    {
        $method = PaymentMethod::from($attributes['payment_method']);
        $invoice = $this->invoice($attributes);
        $payable = $invoice?->invocable ?? $this->payable($attributes);

        return $this->recordPayment->handle(
            invoice: $invoice,
            payable: $payable,
            amount: (float) $attributes['amount'],
            method: $method,
            direction: PaymentDirection::from($attributes['direction']),
            options: $this->options($attributes, $method),
        );
    }

    private function invoice(array $attributes): ?Invoice
    {
        if (empty($attributes['invoice_id'])) {
            return null;
        }

        return Invoice::findOrFail($attributes['invoice_id']);
    }

    private function payable(array $attributes): Model
    {
        $payableType = $attributes['payable_type'] ?? null;

        if (! in_array($payableType, self::PAYABLE_TYPES, true)) {
            throw ValidationException::withMessages([
                'payable_type' => 'Invalid payable type.',
            ]);
        }

        return $payableType::findOrFail($attributes['payable_id']);
    }

    private function options(array $attributes, PaymentMethod $method): array
    {
        return [
            'reference' => $attributes['reference'] ?? null,
            'notes' => $attributes['notes'] ?? null,
            'paid_at' => $attributes['paid_at'] ?? null,
            'metadata' => $method === PaymentMethod::BankTransfer ? $this->bankTransferMetadata($attributes) : null,
            'receipt_path' => $method === PaymentMethod::BankTransfer ? $this->receiptPath($attributes) : null,
            'cheque_due' => $attributes['cheque_due_date'] ?? null,
            'cheque_bank_id' => $attributes['cheque_bank_id'] ?? null,
            'cheque_reference' => $attributes['cheque_number'] ?? null,
            'treasury_account_id' => $attributes['treasury_account_id'] ?? null,
        ];
    }

    private function bankTransferMetadata(array $attributes): array
    {
        return ['bank_name' => $attributes['bank_name'] ?? null];
    }

    private function receiptPath(array $attributes): ?string
    {
        return $this->resolveTemporaryUpload($attributes['receipt'] ?? null, 'receipts', disk: 'public');
    }
}
