<?php

namespace App\Http\Controllers\Contacts;

use App\Exports\SupplierExport;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;

class SupplierExportController extends Controller
{
    public function store()
    {
        return Excel::download(new SupplierExport, 'suppliers.xlsx');
    }
}
