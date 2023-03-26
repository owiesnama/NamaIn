<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Imports\ProductImport;
use Maatwebsite\Excel\Facades\Excel;

class ProductsController extends Controller
{
    public function index()
    {
        return inertia('Products/Index', [
            'products_count' => Product::count(),
            'products' => Product::search(request('search'))
                ->latest()
                ->paginate(parent::ELEMENTS_PER_PAGE)
                ->withQueryString(),
        ]);
    }

    public function store()
    {
        $data = request()->validate([
            'name' => 'required',
            'cost' => 'required|numeric|gt:0',
            'expire_date' => 'required|strtotime',
            'units' => 'array|min:1',
            'units.*.name' => 'required',
            'units.*.conversionFactor' => 'required|numeric|gt:0',
        ]);

        $product = Product::create([
            'name' => $data['name'],
            'cost' => $data['cost'],
            'expire_date' => $data['expire_date'],
        ]);

        $units = collect($data['units'])->map(function ($unit) {
            return [
                'name' => $unit['name'],
                'conversion_factor' => $unit['conversionFactor'],
            ];
        });

        $product->units()->createMany($units->toArray());

        return redirect()->route('products.index')->with('success', 'A Product has been Created');
    }

    public function import()
    {
        Excel::import(new ProductImport(), request()->file('file'));

        return back()->with('success', 'Imported successfully');
    }
}
