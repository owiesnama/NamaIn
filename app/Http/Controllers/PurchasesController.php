<?php

namespace App\Http\Controllers;

use App\Actions\StorePurchaseAction;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Filters\InvoiceFilter;
use App\Http\Requests\CreateInvoiceRequest;
use App\Models\Bank;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Supplier;

class PurchasesController extends Controller
{
    public function index(InvoiceFilter $filter)
    {
        $invoices = Invoice::forSupplier()
            ->filter($filter)
            ->when(request('sort_by'), function ($query, $sortBy) {
                $query->orderBy(in_array($sortBy, ['id', 'created_at', 'total']) ? $sortBy : 'created_at', request('sort_order', 'desc'));
            }, function ($query) {
                $query->latest();
            })
            ->with(['transactions.product', 'transactions.unit', 'invocable'])
            ->paginate(10)
            ->withQueryString();

        return inertia('Purchases/Index', [
            'invoices' => $invoices,
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
            'banks' => Bank::all(),
        ]);
    }

    public function store(CreateInvoiceRequest $request, StorePurchaseAction $storePurchase)
    {
        $storePurchase->handle(collect($request->all()), $request);

        return redirect()->route('purchases.index')->with('success', 'Purchase created successfully');
    }
}
