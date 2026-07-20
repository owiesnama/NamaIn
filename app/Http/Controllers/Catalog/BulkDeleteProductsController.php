<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkDeleteProductsRequest;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class BulkDeleteProductsController extends Controller
{
    public function __invoke(BulkDeleteProductsRequest $request)
    {
        $this->authorize('deleteAny', Product::class);

        $products = Product::whereIn('id', $request->validated('ids'))->get();

        DB::transaction(function () use ($products) {
            $products->each->delete();
        });

        return back()->with('success', __(':count products deleted', ['count' => $products->count()]));
    }
}
