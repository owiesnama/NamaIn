<?php

namespace App\Http\Controllers\Purchases;

use App\Actions\Purchase\ReceiveGoodsAction;
use App\Actions\StorePurchaseAction;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Filters\InvoiceFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateInvoiceRequest;
use App\Models\Bank;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\TreasuryAccount;
use Illuminate\Http\Request;

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
            'treasury_accounts' => TreasuryAccount::active()->get()->map(fn (TreasuryAccount $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'type' => $a->type->value,
                'type_label' => $a->type->label(),
                'current_balance' => $a->currentBalance(),
            ]),
        ]);
    }

    public function store(CreateInvoiceRequest $request, StorePurchaseAction $storePurchase)
    {
        $storePurchase->handle(collect($request->all()), $request);

        return redirect()->route('purchases.index')->with('success', 'Purchase created successfully');
    }

    public function receive(Transaction $transaction, Request $request, ReceiveGoodsAction $action)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
            'storage_id' => 'required|exists:storages,id',
            'notes' => 'nullable|string',
        ]);

        $storage = Storage::findOrFail($request->storage_id);

        $action->execute($transaction, $storage, $request->quantity, auth()->user(), $request->notes);

        return back()->with('success', __('Goods received successfully'));
    }
}
