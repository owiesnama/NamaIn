<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Stock\RecordAdjustmentAction;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\ManualStockIncreaseNotAllowedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\BulkAdjustStockRequest;
use App\Models\Product;
use App\Models\Storage;

class BulkAdjustStockController extends Controller
{
    public function __invoke(BulkAdjustStockRequest $request, RecordAdjustmentAction $recordAdjustment)
    {
        $storage = Storage::findOrFail($request->validated('storage_id'));

        $this->authorize('manageStock', $storage);

        $delta = (int) $request->validated('delta');
        $type = $request->validated('type');
        $notes = $request->validated('notes');
        $actor = $request->user();

        $products = Product::whereIn('id', $request->validated('ids'))->get();

        $adjusted = 0;
        $skipped = [];

        foreach ($products as $product) {
            // Stock adjustments only apply to physical products.
            if ($product->isService()) {
                $skipped[] = $product->name;

                continue;
            }

            try {
                $recordAdjustment->handle(
                    $storage,
                    $product,
                    $storage->quantityOf($product) + $delta,
                    $type,
                    $actor,
                    $notes
                );
                $adjusted++;
            } catch (InsufficientStockException|ManualStockIncreaseNotAllowedException) {
                $skipped[] = $product->name;
            }
        }

        if ($skipped !== []) {
            return back()->with('warning', __(':count products adjusted, :skipped skipped: :names', [
                'count' => $adjusted,
                'skipped' => count($skipped),
                'names' => implode('، ', $skipped),
            ]));
        }

        return back()->with('success', __(':count products adjusted', ['count' => $adjusted]));
    }
}
