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
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->get('type')))
            ->search($request->get('search'))
            ->latest()
            ->simplePaginate(20);

        return response()->json($products);
    }
}
