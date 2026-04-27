<?php

namespace App\Actions;

use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Enums\TreasuryMovementReason;
use App\Models\Customer;
use App\Models\Invoice;
use App\Traits\HandlesAsyncUploads;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StoreInvoiceAction
{
    use HandlesAsyncUploads;

    public function __construct(private RecordPaymentAction $recordPayment) {}

    public function handle(Collection $data, ?Request $request = null): Invoice
    {
        return DB::transaction(function () use ($data, $request) {
            $isSale = $this->isSale($data);
            $invoice = $isSale ? Invoice::sale($data) : Invoice::purchase($data);

            $this->handlePayment($invoice, $data, $request, $isSale);

            return $invoice;
        });
    }

    private function isSale(Collection $data): bool
    {
        $invocable = $data->get('invocable');

        return ($invocable['type'] ?? null) === Customer::class;
    }

    private function handlePayment(Invoice $invoice, Collection $data, ?Request $request, bool $isSale): void
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

        $options = [
            'reference' => $data->get('payment_reference'),
            'notes' => $method === PaymentMethod::Cash
                ? ($isSale ? 'Cash payment on sale' : 'Cash payment on purchase')
                : $data->get('payment_notes'),
            'metadata' => $method === PaymentMethod::BankTransfer ? ['bank_name' => $data->get('bank_name')] : null,
            'receipt_path' => $method === PaymentMethod::BankTransfer
                ? $this->resolveTemporaryUpload($request?->receipt, 'receipts', disk: 'public')
                : null,
            'cheque_due' => $data->get('cheque_due_date'),
            'cheque_bank_id' => $data->get('cheque_bank_id'),
            'cheque_reference' => $data->get('cheque_number'),
            'treasury_account_id' => $data->get('treasury_account_id'),
        ];

        if (! $isSale) {
            $options['movement_reason'] = TreasuryMovementReason::ExpensePaid;
        }

        $this->recordPayment->handle(
            invoice: $invoice,
            payable: $invoice->invocable,
            amount: $amount,
            method: $method,
            direction: $isSale ? PaymentDirection::In : PaymentDirection::Out,
            options: $options,
        );
    }
}
