<?php

namespace App\Http\Controllers\Catalog;

use App\Exports\ProductExport;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;

class ProductExportController extends Controller
{
    public function store()
    {
        return Excel::download(new ProductExport, 'products.xlsx');
    }
}
