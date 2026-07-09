<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;

class ProductGlobalFavoriteController extends Controller
{
    /**
     * Toggle a product as a store-wide (global) favourite. Gated behind
     * products.update, so managers/owners can manage globals while cashiers
     * (who only hold pos.* permissions) cannot.
     */
    public function update(Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $product->update(['is_global_favorite' => ! $product->is_global_favorite]);

        return back()->with('success', __('Global favourite updated.'));
    }
}
