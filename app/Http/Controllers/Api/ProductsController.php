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
            ->when($request->get('type') === 'service', fn ($query) => $query->with('serviceAddons'))
            // Line-sale pickers (invoices, quotes) exclude bookable services;
            // those are booked, not line-sold.
            ->when($request->boolean('line_sale'), fn ($query) => $query->sellableAsLineItem())
            ->search($request->get('search'))
            ->latest()
            ->simplePaginate(20);

        return response()->json($products);
    }
}
