<?php

namespace App\Http\Controllers\Purchases;

use App\Actions\Purchase\ReceiveGoodsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReceiveGoodsRequest;
use App\Models\Storage;
use App\Models\Transaction;

class PurchaseReceiptController extends Controller
{
    public function store(Transaction $transaction, ReceiveGoodsRequest $request, ReceiveGoodsAction $action)
    {
        $this->authorize('update', $transaction->invoice);
        $storage = Storage::findOrFail($request->storage_id);

        $action->execute($transaction, $storage, $request->quantity, $request->user(), $request->notes);

        return back()->with('success', __('Goods received successfully'));
    }
}
