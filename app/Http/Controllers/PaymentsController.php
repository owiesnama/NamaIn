<?php

namespace App\Http\Controllers;

use App\Actions\RecordPaymentAction;
use App\Enums\PaymentMethod;
use App\Filters\PaymentFilter;
use App\Http\Requests\PaymentRequest;
use App\Models\Bank;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Traits\HandlesAsyncUploads;
use Inertia\Response;

class PaymentsController extends Controller
{
    use HandlesAsyncUploads;

    public function index(PaymentFilter $filter)
    {
        return inertia('Payments/Index', [
            'payments' => Payment::filter($filter)
                ->when(request('sort_by'), function ($query, $sortBy) {
                    $query->orderBy(in_array($sortBy, ['id', 'created_at', 'amount']) ? $sortBy : 'created_at', request('sort_order', 'desc'));
                }, function ($query) {
                    $query->latest();
                })
                ->with(['invoice.invocable', 'payable', 'createdBy'])
                ->paginate(10)
                ->withQueryString(),
        ]);
    }

    public function create()
    {
        // Get all customers
        $customers = Customer::orderBy('name')->get();

        // Get all suppliers
        $suppliers = Supplier::orderBy('name')->get();

        // Get all banks
        $banks = Bank::orderBy('name')->get();

        return inertia('Payments/Create', [
            'customers' => $customers,
            'suppliers' => $suppliers,
            'banks' => $banks,
            'payment_methods' => PaymentMethod::casesWithLabels(),
        ]);
    }

    public function store(PaymentRequest $request, RecordPaymentAction $recordPayment)
    {
        $method = PaymentMethod::from($request->payment_method);
        $invoice = $request->invoice_id ? Invoice::findOrFail($request->invoice_id) : null;
        $payable = $invoice
            ? $invoice->invocable
            : ($request->payable_type)::findOrFail($request->payable_id);

        $recordPayment->handle(
            invoice: $invoice,
            payable: $payable,
            amount: $request->amount,
            method: $method,
            options: [
                'reference' => $request->reference,
                'notes' => $request->notes,
                'paid_at' => $request->paid_at,
                'metadata' => $method === PaymentMethod::BankTransfer ? ['bank_name' => $request->bank_name] : null,
                'receipt_path' => $method === PaymentMethod::BankTransfer
                    ? $this->resolveTemporaryUpload($request->receipt, 'receipts', disk: 'public')
                    : null,
                'cheque_due' => $request->cheque_due_date,
                'cheque_bank_id' => $request->cheque_bank_id,
                'cheque_reference' => $request->cheque_number,
            ]
        );

        return redirect()
            ->route('payments.index')
            ->with('success', 'Payment recorded successfully');
    }

    public function show(Payment $payment): Response
    {
        return inertia('Payments/Show', [
            'payment' => $payment->load([
                'invoice.invocable',
                'invoice.payments',
                'payable',
                'createdBy',
            ]),
        ]);
    }
}
