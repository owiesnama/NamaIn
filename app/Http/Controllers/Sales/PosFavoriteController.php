<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\PosSession;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class PosFavoriteController extends Controller
{
    /**
     * Toggle the current user's personal favourite for a product: attach when
     * absent, detach when present. Returns the new state so the client can
     * reconcile its optimistic star update.
     */
    public function toggle(Product $product): JsonResponse
    {
        $this->authorize('operate', PosSession::class);

        $changes = auth()->user()->favorites()->toggle($product->id);

        return response()->json([
            'favorited' => ! empty($changes['attached']),
        ]);
    }
}
