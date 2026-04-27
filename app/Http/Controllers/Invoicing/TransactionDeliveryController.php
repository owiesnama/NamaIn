<?php

namespace App\Http\Controllers\Invoicing;

use App\Actions\Stock\DeliverTransactionAction;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\Storage;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionDeliveryController extends Controller
{
    public function store(Transaction $transaction, Request $request, DeliverTransactionAction $action)
    {
        $request->validate([
            'storage_id' => 'required|exists:storages,id',
        ]);

        $storage = Storage::findOrFail($request->storage_id);

        try {
            $action->execute($transaction, auth()->user(), $storage);
        } catch (InsufficientStockException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Item marked as delivered'));
    }
}
