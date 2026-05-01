<?php

namespace App\Http\Controllers\Contacts;

use App\Http\Controllers\Controller;
use App\Imports\CustomerImport;
use App\Services\CsvSampleGenerator;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CustomerImportController extends Controller
{
    protected array $importHeaders = ['name', 'address', 'phone_number', 'credit_limit', 'opening_balance'];

    protected array $importSampleData = ['Example Customer', 'Customer Address 123', '0123456789', '5000', '1000'];

    public function store()
    {
        Excel::import(new CustomerImport, request()->file('file'));

        return back()->with('success', 'Imported successfully');
    }

    public function show(): BinaryFileResponse
    {
        return (new CsvSampleGenerator)->generate(
            'customers_import_sample.csv',
            $this->importHeaders,
            $this->importSampleData
        );
    }
}
