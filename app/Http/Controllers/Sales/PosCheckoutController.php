<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Pos\ProcessPosCheckoutAction;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\PosCheckoutRequest;
use App\Models\PosSession;
use App\ValueObjects\CheckoutContext;
use DomainException;

class PosCheckoutController extends Controller
{
    public function store(PosCheckoutRequest $request, ProcessPosCheckoutAction $action)
    {
        $this->authorize('create', PosSession::class);
        $session = PosSession::findOrFail($request->session_id);

        try {
            $invoice = $action->handle(
                $session,
                collect($request->validated()),
                $request->user(),
                $request->idempotency_key,
                $request->boolean('acknowledge_transfers'),
                CheckoutContext::cloudWeb($session->tenant_id)
            );
        } catch (InsufficientStockException|DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('pos.index')
            ->with('success', __('Checkout successful.'))
            ->with('last_invoice_id', $invoice->id);
    }
}
