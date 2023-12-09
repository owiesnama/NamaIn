<?php

namespace App\Http\Controllers;

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
            'invoices' => Invoice::where('invoicable_type', Customer::class)->latest()->with('transactions')->paginate(10)->withQueryString(),
            'storages' => Storage::all(),
        ]);
    }

    public function create()
    {
        return inertia('Sales/Create', [
            'products' => Product::with('units')->get(),
        ]);
    }

    public function store(CreateInvoiceRequest $request)
    {
        Invoice::sale(collect($request->all()))
            ->save();

        return redirect()->route('sales.index');
    }
}
