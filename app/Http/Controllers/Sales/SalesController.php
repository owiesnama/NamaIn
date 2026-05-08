<?php

namespace App\Http\Controllers\Sales;

use App\Actions\StoreInvoiceAction;
use App\Enums\PaymentMethod;
use App\Filters\InvoiceFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateInvoiceRequest;
use App\Models\Bank;
use App\Models\Invoice;
use App\Models\Storage;
use App\Models\TreasuryAccount;

class SalesController extends Controller
{
    public function index(InvoiceFilter $filter)
    {
        $this->authorize('viewAny', Invoice::class);

        return inertia('Sales/Index', [
            'invoices' => Invoice::forCustomer()
                ->notFromPos()
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
        $this->authorize('create', Invoice::class);

        return inertia('Sales/Create', [
            'payment_methods' => PaymentMethod::casesWithLabels(),
            'banks' => Bank::all(),
            'treasury_accounts' => TreasuryAccount::active()->withCurrentBalance()->get()->map(fn (TreasuryAccount $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'type' => $a->type->value,
                'type_label' => $a->type->label(),
                'current_balance' => $a->current_balance,
            ]),
        ]);
    }

    public function store(CreateInvoiceRequest $request, StoreInvoiceAction $storeInvoice)
    {
        $this->authorize('create', Invoice::class);

        $storeInvoice->handle(collect($request->validated()));

        return redirect()->route('sales.index')->with('success', __('Sale created successfully'));
    }
}
