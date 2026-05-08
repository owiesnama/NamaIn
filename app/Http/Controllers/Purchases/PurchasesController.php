<?php

namespace App\Http\Controllers\Purchases;

use App\Actions\StoreInvoiceAction;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Filters\InvoiceFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateInvoiceRequest;
use App\Models\Bank;
use App\Models\Invoice;
use App\Models\Storage;
use App\Models\TreasuryAccount;

class PurchasesController extends Controller
{
    public function index(InvoiceFilter $filter)
    {
        $this->authorize('viewAny', Invoice::class);

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
        $this->authorize('create', Invoice::class);

        return inertia('Purchases/Create', [
            'payment_methods' => PaymentMethod::casesWithLabels(),
            'banks' => Bank::all(),
            'treasury_accounts' => TreasuryAccount::active()->withCurrentBalance()->get()->map(fn (TreasuryAccount $account) => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type->value,
                'type_label' => $account->type->label(),
                'current_balance' => $account->currentBalance(),
            ]),
        ]);
    }

    public function store(CreateInvoiceRequest $request, StoreInvoiceAction $storeInvoice)
    {
        $this->authorize('create', Invoice::class);

        $storeInvoice->handle(collect($request->validated()));

        return redirect()->route('purchases.index')->with('success', 'Purchase created successfully');
    }
}
