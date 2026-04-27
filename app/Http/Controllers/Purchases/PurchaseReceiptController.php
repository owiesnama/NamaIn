<?php

namespace App\Http\Controllers\Purchases;

use App\Actions\Purchase\ReceiveGoodsAction;
use App\Http\Controllers\Controller;
use App\Models\Storage;
use App\Models\Transaction;
use Illuminate\Http\Request;

class PurchaseReceiptController extends Controller
{
    public function store(Transaction $transaction, Request $request, ReceiveGoodsAction $action)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
            'storage_id' => 'required|exists:storages,id',
            'notes' => 'nullable|string',
        ]);

        $storage = Storage::findOrFail($request->storage_id);

        $action->execute($transaction, $storage, $request->quantity, auth()->user(), $request->notes);

        return back()->with('success', __('Goods received successfully'));
    }
}
