<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Stock\RecordAdjustmentAction;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StockAdjustmentRequest;
use App\Models\Product;
use App\Models\Storage;

class StockAdjustmentController extends Controller
{
    public function store(Storage $storage, Product $product, StockAdjustmentRequest $request, RecordAdjustmentAction $recordAdjustment)
    {
        $this->authorize('manageStock', $storage);

        try {
            $recordAdjustment->handle(
                $storage,
                $product,
                $request->validated('new_quantity'),
                $request->validated('type'),
                auth()->user(),
                $request->validated('notes')
            );
        } catch (InsufficientStockException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Stock adjusted successfully'));
    }
}
