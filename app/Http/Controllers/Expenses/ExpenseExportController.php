<?php

namespace App\Http\Controllers\Expenses;

use App\Exports\ExpenseExport;
use App\Filters\ExpenseFilter;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;

class ExpenseExportController extends Controller
{
    public function store(ExpenseFilter $filter)
    {
        return Excel::download(new ExpenseExport($filter), 'expenses.xlsx');
    }
}
