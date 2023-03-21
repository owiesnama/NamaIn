<?php

namespace App\Http\Controllers;

use App\Imports\ProductImport;
use App\Models\Product;
use Maatwebsite\Excel\Facades\Excel;

class ProductsController extends Controller
{
    public function index()
    {
        return inertia('Products', [
            'products' => Product::search(request('search'))
                ->latest()
                ->paginate(10)
                ->withQueryString(),
        ]);
    }

    public function import()
    {
        Excel::import(new ProductImport, request()->file('file'));

        return back()->with('flash', ['message' => 'Imported successfully']);
    }
}
