<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Http\Requests\CreateInvoiceRequest;
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
        ]);
    }

    public function store(CreateInvoiceRequest $request)
    {
        Invoice::purchase(collect($request->all()))
            ->save();

        return redirect()->route('purchases.index');
    }
}
