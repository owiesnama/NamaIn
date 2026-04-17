<?php

namespace App\Http\Controllers;

use App\Actions\CreateChequeAction;
use App\Enums\PaymentMethod;
use App\Filters\PaymentFilter;
use App\Http\Requests\PaymentRequest;
use App\Models\Bank;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
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

    public function store(PaymentRequest $request, CreateChequeAction $createCheque)
    {
        $metadata = null;
        $receiptPath = null;

        if ($request->payment_method === PaymentMethod::BankTransfer->value) {
            $metadata = ['bank_name' => $request->bank_name];
            $receiptPath = $this->resolveTemporaryUpload($request->receipt, 'receipts', disk: 'public');
        }

        if ($request->invoice_id) {
            $invoice = Invoice::findOrFail($request->invoice_id);

            $payment = $invoice->recordPayment(
                amount: $request->amount,
                method: PaymentMethod::from($request->payment_method),
                reference: $request->reference,
                notes: $request->notes,
                metadata: $metadata,
                receiptPath: $receiptPath,
                paidAt: $request->paid_at
            );
        } else {
            $payment = Payment::create([
                'payable_id' => $request->payable_id,
                'payable_type' => $request->payable_type,
                'amount' => $request->amount,
                'payment_method' => PaymentMethod::from($request->payment_method),
                'reference' => $request->reference,
                'notes' => $request->notes,
                'paid_at' => $request->paid_at ?? now(),
                'created_by' => auth()->id(),
                'metadata' => $metadata,
                'receipt_path' => $receiptPath,
            ]);
        }

        if ($request->payment_method === PaymentMethod::Cheque->value) {
            $payee = $request->invoice_id
                ? $invoice->invocable
                : $payment->payable;

            $createCheque->execute($payee, [
                'amount' => $request->amount,
                'type' => $payee instanceof Supplier ? 0 : 1, // 1 for Receivable (Customer), 0 for Payable (Supplier)
                'due' => $request->cheque_due_date,
                'bank_id' => $request->cheque_bank_id,
                'reference_number' => $request->cheque_number,
                'invoice_id' => $request->invoice_id,
            ]);
        }

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
