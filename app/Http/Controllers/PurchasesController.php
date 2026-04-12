<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Http\Requests\CreateInvoiceRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Supplier;

class PurchasesController extends Controller
{
    public function index()
    {
        return inertia('Purchases/Index', [
            'invoices' => Invoice::where('invocable_type', Supplier::class)
                ->latest()
                ->with('transactions')
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
            'suppliers' => Supplier::latest()->limit(10)->get(),
            'payment_methods' => PaymentMethod::casesWithLabels(),
        ]);
    }

    public function store(CreateInvoiceRequest $request)
    {
        $invoice = Invoice::purchase(collect($request->all()));
        $invoice->save();

        // Handle payment if cash payment
        if ($request->payment_method === 'cash') {
            $invoice->recordPayment(
                amount: $invoice->total - $invoice->discount,
                method: PaymentMethod::Cash,
                reference: $request->payment_reference,
                notes: 'Cash payment on purchase'
            );
        } elseif ($request->payment_method && $request->initial_payment_amount > 0) {
            // Handle partial/initial payment for other methods
            $invoice->recordPayment(
                amount: $request->initial_payment_amount,
                method: PaymentMethod::from($request->payment_method),
                reference: $request->payment_reference,
                notes: $request->payment_notes
            );
        }

        return redirect()->route('purchases.index')->with('success', 'Purchase created successfully');
    }
}
