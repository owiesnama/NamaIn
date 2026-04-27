<?php

namespace App\Http\Controllers\Contacts;

use App\Exports\CustomerExport;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;

class CustomerExportController extends Controller
{
    public function store()
    {
        return Excel::download(new CustomerExport, 'customers.xlsx');
    }
}
