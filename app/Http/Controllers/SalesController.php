<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Http\Requests\CreateInvoiceRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Storage;

class SalesController extends Controller
{
    public function index()
    {
        return inertia('Sales/Index', [
            'invoices' => Invoice::where('invocable_type', Customer::class)
                ->latest()
                ->with(['transactions', 'invocable'])
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
        ]);
    }

    public function store(CreateInvoiceRequest $request)
    {
        $invoice = Invoice::sale(collect($request->all()));
        $invoice->save();

        // Handle payment if cash payment
        if ($request->payment_method === 'cash') {
            $invoice->recordPayment(
                amount: $invoice->total - $invoice->discount,
                method: PaymentMethod::Cash,
                reference: $request->payment_reference,
                notes: 'Cash payment on sale'
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

        return redirect()->route('sales.index')->with('success', 'Sale created successfully');
    }
}
