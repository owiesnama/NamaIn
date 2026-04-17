<?php

namespace App\Http\Controllers;

use App\Actions\CreateChequeAction;
use App\Enums\PaymentMethod;
use App\Filters\InvoiceFilter;
use App\Http\Requests\CreateInvoiceRequest;
use App\Models\Bank;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Storage;
use App\Traits\HandlesAsyncUploads;

class SalesController extends Controller
{
    use HandlesAsyncUploads;

    public function index(InvoiceFilter $filter)
    {
        return inertia('Sales/Index', [
            'invoices' => Invoice::where('invocable_type', Customer::class)
                ->filter($filter)
                ->when(request('sort_by'), function ($query, $sortBy) {
                    $query->orderBy(in_array($sortBy, ['id', 'created_at', 'total']) ? $sortBy : 'created_at', request('sort_order', 'desc'));
                }, function ($query) {
                    $query->latest();
                })
                ->with(['transactions.product', 'transactions.unit', 'invocable'])
                ->paginate(10)
                ->withQueryString(),
            'storages' => Storage::all(),
        ]);
    }

    public function create()
    {
        $query = Customer::query();

        if (request('customer')) {
            $query->search(request('customer'));
        }

        return inertia('Sales/Create', [
            'products' => Product::with('units')->get(),
            'customers' => $query->latest()->limit(10)->get(),
            'payment_methods' => PaymentMethod::casesWithLabels(),
            'banks' => Bank::all(),
        ]);
    }

    public function store(CreateInvoiceRequest $request, CreateChequeAction $createCheque)
    {
        $invoice = Invoice::sale(collect($request->all()));
        $invoice->save();

        // Handle payment
        if ($request->payment_method === 'cash') {
            $invoice->recordPayment(
                amount: $invoice->total - $invoice->discount,
                method: PaymentMethod::Cash,
                reference: $request->payment_reference,
                notes: 'Cash payment on sale'
            );
        } elseif ($request->payment_method && $request->initial_payment_amount > 0) {
            $metadata = null;
            $receiptPath = null;

            // Handle bank transfer
            if ($request->payment_method === PaymentMethod::BankTransfer->value) {
                $metadata = ['bank_name' => $request->bank_name];
                $receiptPath = $this->resolveTemporaryUpload($request->receipt, 'receipts', disk: 'public');
            }

            // Record payment
            $payment = $invoice->recordPayment(
                amount: $request->initial_payment_amount,
                method: PaymentMethod::from($request->payment_method),
                reference: $request->payment_reference,
                notes: $request->payment_notes,
                metadata: $metadata,
                receiptPath: $receiptPath
            );

            // Handle cheque creation
            if ($request->payment_method === PaymentMethod::Cheque->value) {
                $createCheque->execute($invoice->invocable, [
                    'amount' => $request->initial_payment_amount,
                    'type' => 1, // Receivable
                    'due' => $request->cheque_due_date,
                    'bank_id' => $request->cheque_bank_id,
                    'reference_number' => $request->cheque_number,
                    'invoice_id' => $invoice->id,
                ]);
            }
        }

        return redirect()->route('sales.index')->with('success', 'Sale created successfully');
    }
}
