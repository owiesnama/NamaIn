<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Http\Requests\PaymentRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;

class PaymentsController extends Controller
{
    public function index()
    {
        return inertia('Payments/Index', [
            'payments' => Payment::query()
                ->search(request('search'))
                ->trash(request('status'))
                ->when(request('sort_by'), function ($query, $sortBy) {
                    $query->orderBy(in_array($sortBy, ['id', 'created_at', 'amount']) ? $sortBy : 'created_at', request('sort_order', 'desc'));
                }, function ($query) {
                    $query->latest();
                })
                ->with(['invoice.invocable', 'createdBy'])
                ->paginate(10)
                ->withQueryString(),
        ]);
    }

    public function create()
    {
        // Get all customers with their unpaid/partially paid invoices
        $customers = Customer::with(['invoices' => function ($query) {
            $query->whereIn('payment_status', ['unpaid', 'partially_paid'])
                ->orderBy('created_at', 'desc');
        }])
            ->whereHas('invoices', function ($query) {
                $query->whereIn('payment_status', ['unpaid', 'partially_paid']);
            })
            ->orderBy('name')
            ->get()
            ->map(function ($customer) {
                // Only include customers who actually have unpaid invoices
                if ($customer->invoices->count() > 0) {
                    return $customer;
                }

                return null;
            })
            ->filter();

        return inertia('Payments/Create', [
            'customers' => $customers->values(),
            'payment_methods' => PaymentMethod::casesWithLabels(),
        ]);
    }

    public function store(PaymentRequest $request)
    {
        $invoice = Invoice::findOrFail($request->invoice_id);

        $invoice->recordPayment(
            amount: $request->amount,
            method: PaymentMethod::from($request->payment_method),
            reference: $request->reference,
            notes: $request->notes
        );

        return redirect()
            ->route('payments.index')
            ->with('success', 'Payment recorded successfully');
    }

    public function show(Payment $payment)
    {
        $payment->load(['invoice.invocable', 'invoice.payments', 'createdBy']);

        return inertia('Payments/Show', [
            'payment' => $payment,
        ]);
    }
}
