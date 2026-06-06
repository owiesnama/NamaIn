<?php

namespace App\Http\Controllers\Sales;

use App\Actions\StoreInvoiceAction;
use App\Actions\UpdateInvoiceAction;
use App\Enums\PaymentMethod;
use App\Filters\InvoiceFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Models\Bank;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Quote;
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
            'prefill' => $this->prefillFromQuote(),
        ]);
    }

    private function prefillFromQuote(): ?array
    {
        $quoteId = request()->integer('from_quote');

        if (! $quoteId) {
            return null;
        }

        $quote = Quote::with(['customer', 'items.product', 'items.unit'])->findOrFail($quoteId);

        return [
            'from_quote_id' => $quote->id,
            'customer' => $quote->customer,
            'discount' => $quote->discount,
            'items' => $quote->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product' => $item->product,
                'unit_id' => $item->unit_id,
                'unit' => $item->unit,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
            ]),
        ];
    }

    public function store(CreateInvoiceRequest $request, StoreInvoiceAction $storeInvoice)
    {
        $this->authorize('create', Invoice::class);

        $invoice = $storeInvoice->handle(collect($request->validated()));

        if ($fromQuoteId = $request->integer('from_quote_id')) {
            $quote = Quote::find($fromQuoteId);
            $quote?->markAsConverted($invoice);
        }

        return redirect()->route('sales.index')->with('success', __('Sale created successfully'));
    }

    public function edit(Invoice $invoice)
    {
        abort_unless($invoice->isSale(), 404);
        abort_unless($invoice->isEditable(), 403);
        $this->authorize('update', $invoice);

        $invoice->load(['transactions.product', 'transactions.unit', 'invocable']);

        return inertia('Sales/Edit', [
            'invoice' => $invoice,
            'payment_methods' => PaymentMethod::casesWithLabels(),
            'banks' => Bank::all(),
            'treasury_accounts' => TreasuryAccount::active()->withCurrentBalance()->get()->map(fn (TreasuryAccount $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'type' => $a->type->value,
                'type_label' => $a->type->label(),
                'current_balance' => $a->current_balance,
            ]),
            'prefill' => [
                'customer' => $invoice->invocable,
                'discount' => $invoice->discount,
                'payment_method' => $invoice->payment_method?->value,
                'items' => $invoice->transactions->map(fn ($transaction) => [
                    'product' => $transaction->product,
                    'product_id' => $transaction->product_id,
                    'unit' => $transaction->unit,
                    'unit_id' => $transaction->unit_id,
                    'quantity' => $transaction->quantity,
                    'unit_price' => $transaction->price,
                    'description' => $transaction->description,
                ]),
            ],
        ]);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice, UpdateInvoiceAction $updateInvoice)
    {
        abort_unless($invoice->isSale(), 404);
        abort_unless($invoice->isEditable(), 403);
        $this->authorize('update', $invoice);
        abort_unless($request->input('invocable.type') === Customer::class, 422);

        $updateInvoice->handle($invoice, collect($request->validated()));

        return redirect()->route('invoices.show', $invoice)->with('success', __('Sale updated successfully'));
    }
}
