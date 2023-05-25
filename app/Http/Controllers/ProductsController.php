<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Imports\ProductImport;
use App\Models\Product;
use Maatwebsite\Excel\Facades\Excel;

class ProductsController extends Controller
{
    public function index()
    {
        return inertia('Products/Index', [
            'products_count' => Product::count(),
            'products' => Product::search(request('search'))
                ->with('units')
                ->latest()
                ->paginate(parent::ELEMENTS_PER_PAGE)
                ->withQueryString(),
        ]);
    }

    public function store(ProductRequest $request)
    {
        $product = Product::create($request->all());
        $product->units()->createMany($request->get('units'));

        return redirect()->route('products.index')->with('success', 'Product has been Created Successfully');
    }

    public function update(Product $product, ProductRequest $request)
    {
        $product->update($request->all());

        return back()->with('success', 'Product updated successfully');
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return back()->with('success', 'Product Deleted successfully');
    }

    public function import()
    {
        Excel::import(new ProductImport(), request()->file('file'));

        return back()->with('success', 'Imported successfully');
    }
}
