<?php

namespace App\Http\Controllers;

use App\Enums\ChequeStatus;
use App\Enums\PaymentMethod;
use App\Filters\PaymentFilter;
use App\Http\Requests\PaymentRequest;
use App\Models\Bank;
use App\Models\Cheque;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Supplier;

class PaymentsController extends Controller
{
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

    public function store(PaymentRequest $request)
    {
        $metadata = null;
        $receiptPath = null;

        if ($request->payment_method === PaymentMethod::BankTransfer->value) {
            $metadata = ['bank_name' => $request->bank_name];
            if ($request->hasFile('receipt')) {
                $receiptPath = $request->file('receipt')->store('receipts', 'public');
            }
        }

        if ($request->invoice_id) {
            $invoice = Invoice::findOrFail($request->invoice_id);

            $payment = $invoice->recordPayment(
                amount: $request->amount,
                method: PaymentMethod::from($request->payment_method),
                reference: $request->reference,
                notes: $request->notes,
                metadata: $metadata,
                receiptPath: $receiptPath
            );
        } else {
            $payment = Payment::create([
                'payable_id' => $request->payable_id,
                'payable_type' => $request->payable_type,
                'amount' => $request->amount,
                'payment_method' => PaymentMethod::from($request->payment_method),
                'reference' => $request->reference,
                'notes' => $request->notes,
                'paid_at' => now(),
                'created_by' => auth()->id(),
                'metadata' => $metadata,
                'receipt_path' => $receiptPath,
            ]);
        }

        if ($request->payment_method === PaymentMethod::Cheque->value) {
            $payee = $request->invoice_id
                ? $invoice->invocable
                : $payment->payable;

            Cheque::create([
                'chequeable_id' => $payee->id,
                'chequeable_type' => get_class($payee),
                'amount' => $request->amount,
                'type' => $payee instanceof Supplier ? 2 : 1, // 1 for Debit (Customer), 2 for Credit (Supplier)
                'due' => $request->cheque_due_date,
                'bank' => $this->bankName($request->cheque_bank_id),
                'bank_id' => $request->cheque_bank_id,
                'reference_number' => $request->cheque_number,
                'status' => ChequeStatus::Drafted,
            ]);
        }

        return redirect()
            ->route('payments.index')
            ->with('success', 'Payment recorded successfully');
    }

    protected function bankName($bankId)
    {
        return Bank::find($bankId)?->name ?? 'Unknown';
    }

    public function show(Payment $payment)
    {
        $payment->load(['invoice.invocable', 'invoice.payments', 'createdBy']);

        return inertia('Payments/Show', [
            'payment' => $payment,
        ]);
    }
}
