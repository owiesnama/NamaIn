<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Stock\RecordAdjustmentAction;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Storage;
use Illuminate\Http\Request;

class StockAdjustmentController extends Controller
{
    public function store(Storage $storage, Product $product, Request $request, RecordAdjustmentAction $action)
    {
        $this->authorize('manageStock', $storage);

        $request->validate([
            'new_quantity' => 'required|integer|min:0',
            'type' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        try {
            $action->execute(
                $storage,
                $product,
                $request->new_quantity,
                $request->type,
                auth()->user(),
                $request->notes
            );
        } catch (InsufficientStockException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Stock adjusted successfully'));
    }
}
