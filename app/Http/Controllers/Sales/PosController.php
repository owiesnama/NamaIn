<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Pos\ClosePosSessionAction;
use App\Actions\Pos\OpenPosSessionAction;
use App\Actions\Pos\PosPreflightAction;
use App\Actions\Pos\ProcessPosCheckoutAction;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\PosSession;
use App\Models\Storage;
use Illuminate\Http\Request;

class PosController extends Controller
{
    public function open(Request $request, OpenPosSessionAction $action)
    {
        $request->validate([
            'storage_id' => 'required|exists:storages,id',
            'opening_float' => 'required|numeric|min:0',
        ]);

        $storage = Storage::findOrFail($request->storage_id);

        $action->execute($storage, $request->opening_float * 100, auth()->user());

        return redirect()->route('pos.index')->with('success', __('POS session opened.'));
    }

    public function checkout(Request $request, ProcessPosCheckoutAction $action, PosPreflightAction $preflight)
    {
        $request->validate([
            'session_id' => 'required|exists:pos_sessions,id',
            'customer_id' => 'nullable|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.unit_id' => 'nullable|exists:units,id',
            'total' => 'required|numeric',
            'idempotency_key' => 'nullable|string',
            'acknowledge_transfers' => 'nullable|boolean',
        ]);

        $session = PosSession::findOrFail($request->session_id);

        if (! $request->boolean('acknowledge_transfers')) {
            $preflightResult = $preflight->execute($session, $request->items);
            if ($preflightResult['requires_confirmation']) {
                if ($request->inertia()) {
                    return back()->with('response', $preflightResult);
                }

                return response()->json($preflightResult, isset($preflightResult['success']) && ! $preflightResult['success'] ? 422 : 200);
            }
        }

        $data = collect($request->all());
        $data->put('total', $request->total * 100);

        try {
            $invoice = $action->execute(
                $session,
                $data,
                auth()->user(),
                $request->idempotency_key,
                $request->boolean('acknowledge_transfers')
            );
        } catch (InsufficientStockException $e) {
            if ($request->inertia()) {
                return back()->with('error', $e->getMessage());
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        if ($request->inertia()) {
            return redirect()->route('pos.index')
                ->with('success', __('Checkout successful.'))
                ->with('last_invoice_id', $invoice->id);
        }

        return response()->json([
            'success' => true,
            'invoice_id' => $invoice->id,
            'message' => __('Checkout successful.'),
        ]);
    }

    public function close(Request $request, ClosePosSessionAction $action)
    {
        $request->validate([
            'session_id' => 'required|exists:pos_sessions,id',
            'closing_float' => 'required|numeric|min:0',
        ]);

        $session = PosSession::findOrFail($request->session_id);

        $action->execute($session, $request->closing_float * 100, auth()->user());

        return redirect()->route('pos.index')->with('success', __('POS session closed.'));
    }
}
