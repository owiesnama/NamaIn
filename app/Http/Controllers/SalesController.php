<?php

namespace App\Http\Controllers;

use App\Actions\StoreSaleAction;
use App\Enums\PaymentMethod;
use App\Filters\InvoiceFilter;
use App\Http\Requests\CreateInvoiceRequest;
use App\Models\Bank;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Storage;

class SalesController extends Controller
{
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

    public function store(CreateInvoiceRequest $request, StoreSaleAction $storeSale)
    {
        $storeSale->handle(collect($request->all()), $request);

        return redirect()->route('sales.index')->with('success', 'Sale created successfully');
    }
}
