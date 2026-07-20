<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkUpdatePricesRequest;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class BulkUpdatePricesController extends Controller
{
    public function __invoke(BulkUpdatePricesRequest $request)
    {
        $this->authorize('updateAny', Product::class);

        $mode = $request->validated('mode');
        $value = (float) $request->validated('value');

        DB::transaction(function () use ($request, $mode, $value) {
            Product::whereIn('id', $request->validated('ids'))
                ->get()
                ->each(function (Product $product) use ($mode, $value) {
                    // MoneyCast exposes price in major units, so the arithmetic
                    // and assignment both stay in major units.
                    $product->price = $mode === 'percent'
                        ? max(0, round($product->price * (1 + $value / 100), 2))
                        : $value;

                    $product->save();
                });
        });

        return back()->with('success', __('Prices updated'));
    }
}
