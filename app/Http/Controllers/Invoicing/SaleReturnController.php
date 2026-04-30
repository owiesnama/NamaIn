<?php

namespace App\Http\Controllers\Invoicing;

use App\Actions\CreateInverseInvoiceAction;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateInverseInvoiceRequest;
use App\Models\Invoice;

class SaleReturnController extends Controller
{
    public function create(Invoice $invoice)
    {
        $this->authorize('create', Invoice::class);
        abort_unless($invoice->can_be_inversed, 403, 'This invoice cannot be returned.');

        return inertia('Sales/Return', [
            'invoice' => $invoice->load(['transactions.product', 'transactions.unit', 'invocable']),
            'payment_methods' => PaymentMethod::casesWithLabels(),
        ]);
    }

    public function store(CreateInverseInvoiceRequest $request, Invoice $invoice, CreateInverseInvoiceAction $action)
    {
        $this->authorize('create', Invoice::class);
        abort_unless($invoice->can_be_inversed, 403, 'This invoice cannot be returned.');

        $action->handle($invoice, collect($request->validated()), $request->inverse_reason);

        return redirect()->route('sales.index')->with('success', __('Return invoice created successfully'));
    }
}
