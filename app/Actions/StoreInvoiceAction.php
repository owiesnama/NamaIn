<?php

namespace App\Actions;

use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TreasuryMovementReason;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Unit;
use App\Traits\HandlesAsyncUploads;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StoreInvoiceAction
{
    use HandlesAsyncUploads;

    public function __construct(private RecordPaymentAction $recordPayment) {}

    public function handle(Collection $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $isSale = $this->isSale($data);
            $invoice = $this->createInvoiceWithTransactions($data, $isSale);

            $this->handlePayment($invoice, $data, $isSale);

            return $invoice;
        });
    }

    private function createInvoiceWithTransactions(Collection $data, bool $isSale): Invoice
    {
        $invocable = $data->get('invocable');
        $invocableClass = $invocable['type'];
        $invocableModel = $invocableClass::find($invocable['id']);

        $products = collect($data->get('products'));

        $computedTotal = $products->sum(fn ($p) => ($p['price'] * $p['quantity']) - ($p['discount'] ?? 0));

        $invoice = $invocableModel->invoices()->create([
            'total' => $computedTotal,
            'payment_method' => $data->get('payment_method', 'cash'),
            'payment_status' => PaymentStatus::Unpaid,
            'paid_amount' => 0,
            'discount' => $data->get('discount', 0),
        ]);

        $unitIds = $products->pluck('unit')->filter()->unique();
        $units = Unit::whereIn('id', $unitIds)->get()->keyBy('id');

        $productModels = $isSale
            ? Product::whereIn('id', $products->pluck('product')->unique())->get()->keyBy('id')
            : collect();

        $invoice->transactions()->createMany($products->map(function ($product) use ($units, $isSale, $productModels) {
            $data = [
                'product_id' => $product['product'],
                'storage_id' => $product['storage'] ?? null,
                'unit_id' => $product['unit'] ?? null,
                'quantity' => $product['quantity'],
                'price' => $product['price'],
                'discount' => $product['discount'] ?? 0,
                'description' => $product['description'] ?? null,
                'base_quantity' => isset($units[$product['unit'] ?? null])
                    ? $units[$product['unit']]->conversion_factor * $product['quantity']
                    : $product['quantity'],
            ];

            if ($isSale) {
                $productModel = $productModels[$product['product']] ?? null;
                $data['unit_cost'] = $productModel?->average_cost ?? 0;
            }

            return $data;
        }));

        return $invoice;
    }

    private function isSale(Collection $data): bool
    {
        $invocable = $data->get('invocable');

        return ($invocable['type'] ?? null) === Customer::class;
    }

    private function handlePayment(Invoice $invoice, Collection $data, bool $isSale): void
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
                ? $this->resolveTemporaryUpload($data->get('receipt'), 'receipts', disk: 'public')
                : null,
            'cheque_due' => $data->get('cheque_due_date'),
            'cheque_bank_id' => $data->get('cheque_bank_id'),
            'cheque_reference' => $data->get('cheque_number'),
            'treasury_account_id' => $data->get('treasury_account_id'),
        ];

        if (! $isSale) {
            $options['movement_reason'] = TreasuryMovementReason::SupplierPaymentMade;
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
