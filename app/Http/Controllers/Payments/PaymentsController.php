<?php

namespace App\Http\Controllers\Payments;

use App\Actions\RecordPaymentAction;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Filters\PaymentFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest;
use App\Models\Bank;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\TreasuryAccount;
use App\Traits\HandlesAsyncUploads;
use Inertia\Response;

class PaymentsController extends Controller
{
    use HandlesAsyncUploads;

    public function index(PaymentFilter $filter)
    {
        $this->authorize('viewAny', Payment::class);

        $baseQuery = Payment::filter($filter)->with(['invoice.invocable', 'payable', 'createdBy', 'treasuryAccount']);

        $summary = [
            'total_in' => (clone $baseQuery)->where('direction', 'in')->sum('amount'),
            'total_out' => (clone $baseQuery)->where('direction', 'out')->sum('amount'),
        ];

        $payments = (clone $baseQuery)
            ->when(request('sort_by'), function ($query, $sortBy) {
                $query->orderBy(in_array($sortBy, ['id', 'created_at', 'amount']) ? $sortBy : 'created_at', request('sort_order', 'desc'));
            }, function ($query) {
                $query->latest();
            })
            ->paginate(10)
            ->withQueryString();

        return inertia('Payments/Index', [
            'payments' => $payments,
            'summary' => $summary,
            'payment_methods' => PaymentMethod::casesWithLabels(),
        ]);
    }

    public function create()
    {
        $this->authorize('create', Payment::class);

        $customers = Customer::orderBy('name')->get();
        $suppliers = Supplier::orderBy('name')->get();
        $banks = Bank::orderBy('name')->get();

        return inertia('Payments/Create', [
            'customers' => $customers,
            'suppliers' => $suppliers,
            'banks' => $banks,
            'payment_methods' => PaymentMethod::casesWithLabels(),
            'treasury_accounts' => TreasuryAccount::active()->get()->map(fn (TreasuryAccount $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'type' => $a->type->value,
                'type_label' => $a->type->label(),
                'current_balance' => $a->currentBalance(),
            ]),
        ]);
    }

    public function store(PaymentRequest $request, RecordPaymentAction $recordPayment)
    {
        $this->authorize('create', Payment::class);

        $method = PaymentMethod::from($request->payment_method);
        $invoice = $request->invoice_id ? Invoice::findOrFail($request->invoice_id) : null;

        abort_unless(
            $invoice || in_array($request->payable_type, [Customer::class, Supplier::class]),
            422,
            'Invalid payable type.'
        );

        $payable = $invoice
            ? $invoice->invocable
            : ($request->payable_type)::findOrFail($request->payable_id);

        $recordPayment->handle(
            invoice: $invoice,
            payable: $payable,
            amount: $request->amount,
            method: $method,
            direction: PaymentDirection::from($request->direction),
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
                'treasury_account_id' => $request->treasury_account_id,
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
