<?php

namespace App\Http\Controllers\Contacts;

use App\Http\Controllers\Controller;
use App\Imports\SupplierImport;
use App\Services\CsvSampleGenerator;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SupplierImportController extends Controller
{
    protected array $importHeaders = ['name', 'address', 'phone_number', 'opening_balance'];

    protected array $importSampleData = ['Example Supplier', 'Supplier Address 123', '0123456789', '1000'];

    public function store()
    {
        Excel::import(new SupplierImport, request()->file('file'));

        return back()->with('success', 'Imported successfully');
    }

    public function show(): BinaryFileResponse
    {
        return (new CsvSampleGenerator)->generate(
            'suppliers_import_sample.csv',
            $this->importHeaders,
            $this->importSampleData
        );
    }
}
