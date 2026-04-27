<?php

namespace App\Actions;

use App\Actions\Stock\ReverseTransactionAction;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CreateInverseInvoiceAction
{
    public function __construct(
        private ReverseTransactionAction $reverseTransaction,
    ) {}

    public function handle(Invoice $invoice, Collection $data, string $reason): Invoice
    {
        return DB::transaction(function () use ($invoice, $data, $reason) {
            $inverseInvoice = $invoice->createInverseInvoice($data, $reason);

            $this->reverseTransactions($inverseInvoice, $data);

            $this->processRefund($inverseInvoice, $invoice, $data);

            $invoice->markAs(InvoiceStatus::Returned);

            return $inverseInvoice;
        });
    }

    private function reverseTransactions(Invoice $inverseInvoice, Collection $data): void
    {
        foreach ($inverseInvoice->transactions as $index => $transaction) {
            $originalTransaction = Transaction::find($data->get('products')[$index]['transaction_id'] ?? null);

            if ($originalTransaction) {
                $transaction->storage_id = $originalTransaction->storage_id;
                $transaction->save();
            }

            $this->reverseTransaction->execute($transaction);
        }
    }

    private function processRefund(Invoice $inverseInvoice, Invoice $originalInvoice, Collection $data): void
    {
        $refundAmount = (float) $data->get('refund_amount', 0);
        $paymentMethod = $data->get('payment_method');

        if ($refundAmount <= 0 || ! $paymentMethod) {
            return;
        }

        $inverseInvoice->recordPayment(
            amount: $refundAmount,
            method: PaymentMethod::from($paymentMethod),
            notes: "Refund for invoice #{$originalInvoice->serial_number}"
        );
    }
}
