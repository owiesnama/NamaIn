<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $products = Product::query()
            ->with('units')
            ->search($request->get('search'))
            ->latest()
            ->simplePaginate(20);

        return response()->json($products);
    }
}
