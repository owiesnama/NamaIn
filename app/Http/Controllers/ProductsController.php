<?php

namespace App\Http\Controllers;

use App\Imports\ProductImport;
use App\Models\Product;
use Maatwebsite\Excel\Facades\Excel;

class ProductsController extends Controller
{
    public function index()
    {
        return inertia('Products/Index', [
            'products' => Product::search(request('search'))
                ->latest()
                ->paginate(10)
                ->withQueryString(),
        ]);
    }

    public function create()
    {
        return inertia('Products/Create');
    }

    public function store()
    {
        $attributes = request()->validate([
            'name' => 'required',
            'cost' => 'required|numeric|gt:0',
            'units' => 'array|min:1',
            'units.*.name' => 'required',
            'units.*.conversionFactor' => 'required|numeric|gt:0',
        ]);

        $product = Product::create([
            'name' => $attributes['name'],
            'cost' => $attributes['cost'],
        ]);
        $units = collect($attributes['units'])->map(function ($unit) {
            return [
                'name' => $unit['name'],
                'conversion_factor' => $unit['conversionFactor'],
            ];
        });

        $product->units()->createMany($units->toArray());

        return redirect()->route('products.index')->with('flash', ['message' => 'A Product has been added']);
    }

    public function import()
    {
        Excel::import(new ProductImport, request()->file('file'));

        return back()->with('flash', ['message' => 'Imported successfully']);
    }
}
