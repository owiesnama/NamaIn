<?php

namespace App\Http\Controllers;

use App\Enums\ChequeStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Filters\InvoiceFilter;
use App\Http\Requests\CreateInvoiceRequest;
use App\Models\Bank;
use App\Models\Cheque;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Supplier;

class PurchasesController extends Controller
{
    public function index(InvoiceFilter $filter)
    {
        return inertia('Purchases/Index', [
            'invoices' => Invoice::where('invocable_type', Supplier::class)
                ->filter($filter)
                ->when(request('sort_by'), function ($query, $sortBy) {
                    $query->orderBy(in_array($sortBy, ['id', 'created_at', 'total']) ? $sortBy : 'created_at', request('sort_order', 'desc'));
                }, function ($query) {
                    $query->latest();
                })
                ->with(['transactions', 'invocable'])
                ->paginate(10)
                ->withQueryString(),
            'storages' => Storage::all(),
            'status' => InvoiceStatus::casesWithLabels(),
        ]);
    }

    public function create()
    {
        return inertia('Purchases/Create', [
            'products' => Product::with('units')->get(),
            'suppliers' => Supplier::search(request('supplier'))->latest()->limit(10)->get(),
            'payment_methods' => PaymentMethod::casesWithLabels(),
            'banks' => \App\Models\Bank::all(),
        ]);
    }

    public function store(CreateInvoiceRequest $request)
    {
        $invoice = Invoice::purchase(collect($request->all()));
        $invoice->save();

        // Handle payment
        if ($request->payment_method === 'cash') {
            $invoice->recordPayment(
                amount: $invoice->total - $invoice->discount,
                method: PaymentMethod::Cash,
                reference: $request->payment_reference,
                notes: 'Cash payment on purchase'
            );
        } elseif ($request->payment_method && $request->initial_payment_amount > 0) {
            $metadata = null;
            $receiptPath = null;

            // Handle bank transfer
            if ($request->payment_method === PaymentMethod::BankTransfer->value) {
                $metadata = ['bank_name' => $request->bank_name];
                if ($request->hasFile('receipt')) {
                    $receiptPath = $request->file('receipt')->store('receipts', 'public');
                }
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
                Cheque::create([
                    'chequeable_id' => $invoice->invocable->id,
                    'chequeable_type' => get_class($invoice->invocable),
                    'amount' => $request->initial_payment_amount,
                    'type' => 2, // 2 for Credit (Supplier)
                    'due' => $request->cheque_due_date,
                    'bank' => Bank::find($request->cheque_bank_id)?->name ?? 'Unknown',
                    'bank_id' => $request->cheque_bank_id,
                    'reference_number' => $request->cheque_number,
                    'status' => ChequeStatus::Drafted,
                ]);
            }
        }

        return redirect()->route('purchases.index')->with('success', 'Purchase created successfully');
    }
}
