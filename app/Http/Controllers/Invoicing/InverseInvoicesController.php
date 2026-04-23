<?php

namespace App\Http\Controllers\Invoicing;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateInverseInvoiceRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class InverseInvoicesController extends Controller
{
    public function searchInvoices()
    {
        $type = request('type', 'sale');
        $query = Invoice::query()
            ->with(['invocable'])
            ->where('is_inverse', false)
            ->where('status', '!=', InvoiceStatus::Returned)
            ->when(request('search'), function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('serial_number', 'like', "%{$search}%")
                        ->orWhereHasMorph('invocable', [Customer::class, Supplier::class], function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->limit(20);

        if ($type === 'sale') {
            $query->where('invocable_type', Customer::class);
        } else {
            $query->where('invocable_type', Supplier::class);
        }

        return response()->json(
            $query->get()->map(fn ($invoice) => [
                'id' => $invoice->id,
                'serial_number' => $invoice->serial_number,
                'invocable_name' => $invoice->invocable?->name,
                'total' => $invoice->total,
                'date' => $invoice->created_at->format('Y-m-d'),
                'return_url' => route($type === 'sale' ? 'sales.return.create' : 'purchases.return.create', $invoice->id),
            ])
        );
    }

    public function createSaleReturn(Invoice $invoice)
    {
        abort_unless($invoice->can_be_inversed, 403, 'This invoice cannot be returned.');

        return inertia('Sales/Return', [
            'invoice' => $invoice->load(['transactions.product', 'transactions.unit', 'invocable']),
            'payment_methods' => PaymentMethod::casesWithLabels(),
        ]);
    }

    public function createPurchaseReturn(Invoice $invoice)
    {
        abort_unless($invoice->can_be_inversed, 403, 'This invoice cannot be returned.');

        return inertia('Purchases/Return', [
            'invoice' => $invoice->load(['transactions.product', 'transactions.unit', 'invocable']),
            'payment_methods' => PaymentMethod::casesWithLabels(),
        ]);
    }

    public function storeSaleReturn(CreateInverseInvoiceRequest $request, Invoice $invoice)
    {
        return $this->storeReturn($request, $invoice, 'sales.index');
    }

    public function storePurchaseReturn(CreateInverseInvoiceRequest $request, Invoice $invoice)
    {
        return $this->storeReturn($request, $invoice, 'purchases.index');
    }

    protected function storeReturn(CreateInverseInvoiceRequest $request, Invoice $invoice, string $redirectRoute)
    {
        abort_unless($invoice->can_be_inversed, 403, 'This invoice cannot be returned.');

        DB::transaction(function () use ($request, $invoice) {
            $inverseInvoice = $invoice->createInverseInvoice(
                collect($request->validated()),
                $request->inverse_reason
            );

            foreach ($inverseInvoice->transactions as $index => $transaction) {
                $originalTransaction = Transaction::find($request->input("products.{$index}.transaction_id"));
                if ($originalTransaction) {
                    $transaction->storage_id = $originalTransaction->storage_id;
                    $transaction->save();
                }

                $transaction->reverse();
            }

            if ($request->refund_amount > 0 && $request->payment_method) {
                $inverseInvoice->recordPayment(
                    amount: $request->refund_amount,
                    method: PaymentMethod::from($request->payment_method),
                    notes: "Refund for invoice #{$invoice->serial_number}"
                );
            }

            $invoice->markAs(InvoiceStatus::Returned);
        });

        return redirect()->route($redirectRoute)->with('success', __('Return invoice created successfully'));
    }
}
