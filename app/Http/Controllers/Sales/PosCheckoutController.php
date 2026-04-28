<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Pos\ProcessPosCheckoutAction;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\PosCheckoutRequest;
use App\Models\PosSession;

class PosCheckoutController extends Controller
{
    public function store(PosCheckoutRequest $request, ProcessPosCheckoutAction $action)
    {
        $session = PosSession::findOrFail($request->session_id);

        try {
            $invoice = $action->execute(                $session,
                collect($request->validated()),

                $request->user(),
                $request->idempotency_key,
                $request->boolean('acknowledge_transfers')
            );
        } catch (InsufficientStockException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('pos.index')
            ->with('success', __('Checkout successful.'))
            ->with('last_invoice_id', $invoice->id);
    }
}
