<?php

namespace App\Actions;

use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Traits\HandlesAsyncUploads;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class StoreSaleAction
{
    use HandlesAsyncUploads;

    public function __construct(private RecordPaymentAction $recordPayment) {}

    public function handle(Collection $data, ?Request $request = null): Invoice
    {
        $invoice = Invoice::sale($data);

        $this->handlePayment($invoice, $data, $request);

        return $invoice;
    }

    private function handlePayment(Invoice $invoice, Collection $data, ?Request $request): void
    {
        $methodValue = $data->get('payment_method');

        if (! $methodValue) {
            return;
        }

        $method = PaymentMethod::from($methodValue);

        $amount = $method === PaymentMethod::Cash
            ? $invoice->total - $invoice->discount
            : (float) $data->get('initial_payment_amount', 0);

        if ($amount <= 0) {
            return;
        }

        $this->recordPayment->handle(
            invoice: $invoice,
            payable: $invoice->invocable,
            amount: $amount,
            method: $method,
            direction: PaymentDirection::In,
            options: [
                'reference' => $data->get('payment_reference'),
                'notes' => $method === PaymentMethod::Cash ? 'Cash payment on sale' : $data->get('payment_notes'),
                'metadata' => $method === PaymentMethod::BankTransfer ? ['bank_name' => $data->get('bank_name')] : null,
                'receipt_path' => $method === PaymentMethod::BankTransfer
                    ? $this->resolveTemporaryUpload($request?->receipt, 'receipts', disk: 'public')
                    : null,
                'cheque_due' => $data->get('cheque_due_date'),
                'cheque_bank_id' => $data->get('cheque_bank_id'),
                'cheque_reference' => $data->get('cheque_number'),
                'treasury_account_id' => $data->get('treasury_account_id'),
            ]
        );
    }
}
