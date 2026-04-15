<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Http\Requests\CreateInverseInvoiceRequest;
use App\Models\Invoice;
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
                        ->orWhereHasMorph('invocable', ['App\Models\Customer', 'App\Models\Supplier'], function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->limit(20);

        if ($type === 'sale') {
            $query->where('invocable_type', 'App\Models\Customer');
        } else {
            $query->where('invocable_type', 'App\Models\Supplier');
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

        DB::beginTransaction();

        try {
            // Create the inverse invoice
            $inverseInvoice = $invoice->createInverseInvoice(
                collect($request->validated()),
                $request->inverse_reason
            );

            // Reverse stock movements
            foreach ($inverseInvoice->transactions as $index => $transaction) {
                // Find corresponding original transaction to get storage_id
                $originalTransaction = Transaction::find($request->input("products.{$index}.transaction_id"));
                if ($originalTransaction) {
                    $transaction->storage_id = $originalTransaction->storage_id;
                    $transaction->save();
                }

                $transaction->reverse();
            }

            // Handle refund payment
            if ($request->refund_amount > 0 && $request->payment_method) {
                $inverseInvoice->recordPayment(
                    amount: $request->refund_amount,
                    method: PaymentMethod::from($request->payment_method),
                    notes: "Refund for invoice #{$invoice->serial_number}"
                );
            }

            // Mark original invoice as Returned (we could add PartiallyReturned if we track qty)
            $invoice->markAs(InvoiceStatus::Returned);

            DB::commit();

            return redirect()->route($redirectRoute)->with('success', 'Return invoice created successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
