<?php

namespace App\Http\Controllers;

use App\Imports\ProductImport;
use Maatwebsite\Excel\Facades\Excel;

class ProductsController extends Controller
{
    public function index()
    {
        return inertia('Products');
    }

    public function import()
    {
        Excel::import(new ProductImport, request()->file('file'));
        return back()->with('success', 'Imported successfully');
    }
}
