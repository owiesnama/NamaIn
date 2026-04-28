<?php

namespace App\Http\Controllers\Payments;

use App\Actions\RecordPaymentEntryAction;
use App\Enums\PaymentMethod;
use App\Filters\PaymentFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest;
use App\Models\Payment;
use App\Queries\PaymentFormOptionsQuery;
use App\Queries\PaymentIndexQuery;
use Inertia\Response;

class PaymentsController extends Controller
{
    public function index(PaymentFilter $filter, PaymentIndexQuery $payments)
    {
        $this->authorize('viewAny', Payment::class);

        return inertia('Payments/Index', [
            'payments' => $payments->paginated($filter, request('sort_by'), request('sort_order', 'desc')),
            'summary' => $payments->summary($filter),
            'payment_methods' => PaymentMethod::casesWithLabels(),
        ]);
    }

    public function create(PaymentFormOptionsQuery $options)
    {
        $this->authorize('create', Payment::class);

        return inertia('Payments/Create', $options->forCreate());
    }

    public function store(PaymentRequest $request, RecordPaymentEntryAction $recordPayment)
    {
        $this->authorize('create', Payment::class);

        $recordPayment->handle($request->validated());

        return redirect()
            ->route('payments.index')
            ->with('success', 'Payment recorded successfully');
    }

    public function show(Payment $payment): Response
    {
        $this->authorize('view', $payment);

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
