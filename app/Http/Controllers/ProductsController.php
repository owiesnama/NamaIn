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
        dd(request()->file());
        Excel::import(new ProductImport, request()->files());
    }
}
