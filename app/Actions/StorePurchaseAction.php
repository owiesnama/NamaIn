<?php

namespace App\Actions;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Traits\HandlesAsyncUploads;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class StorePurchaseAction
{
    use HandlesAsyncUploads;

    public function __construct(
        private CreateChequeAction $createCheque
    ) {}

    public function execute(Collection $data, ?Request $request = null): Invoice
    {
        $invoice = Invoice::purchase($data);
        $invoice->save();

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

        match ($method) {
            PaymentMethod::Cash => $this->payCash($invoice, $data),
            PaymentMethod::BankTransfer => $this->payByBankTransfer($invoice, $data, $request),
            PaymentMethod::Cheque => $this->payByCheque($invoice, $data),
            default => $this->payPartial($invoice, $data),
        };
    }

    private function payCash(Invoice $invoice, Collection $data): void
    {
        $invoice->recordPayment(
            amount: $invoice->total - $invoice->discount,
            method: PaymentMethod::Cash,
            reference: $data->get('payment_reference'),
            notes: 'Cash payment on purchase'
        );
    }

    private function payByBankTransfer(Invoice $invoice, Collection $data, ?Request $request): void
    {
        $amount = (float) $data->get('initial_payment_amount', 0);

        if ($amount <= 0) {
            return;
        }

        $receiptPath = $this->resolveTemporaryUpload($request?->receipt, 'receipts', disk: 'public');

        $invoice->recordPayment(
            amount: $amount,
            method: PaymentMethod::BankTransfer,
            reference: $data->get('payment_reference'),
            notes: $data->get('payment_notes'),
            metadata: ['bank_name' => $data->get('bank_name')],
            receiptPath: $receiptPath,
        );
    }

    private function payByCheque(Invoice $invoice, Collection $data): void
    {
        $amount = (float) $data->get('initial_payment_amount', 0);

        if ($amount <= 0) {
            return;
        }

        $invoice->recordPayment(
            amount: $amount,
            method: PaymentMethod::Cheque,
            reference: $data->get('payment_reference'),
            notes: $data->get('payment_notes'),
        );

        $this->createCheque->execute($invoice->invocable, [
            'amount' => $amount,
            'type' => 0,
            'due' => $data->get('cheque_due_date'),
            'bank_id' => $data->get('cheque_bank_id'),
            'reference_number' => $data->get('cheque_number'),
            'invoice_id' => $invoice->id,
        ]);
    }

    private function payPartial(Invoice $invoice, Collection $data): void
    {
        $amount = (float) $data->get('initial_payment_amount', 0);

        if ($amount <= 0) {
            return;
        }

        $invoice->recordPayment(
            amount: $amount,
            method: PaymentMethod::from($data->get('payment_method')),
            reference: $data->get('payment_reference'),
            notes: $data->get('payment_notes'),
        );
    }
}
