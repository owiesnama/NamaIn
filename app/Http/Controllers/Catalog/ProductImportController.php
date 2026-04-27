<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Imports\ProductImport;
use App\Services\CsvSampleGenerator;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductImportController extends Controller
{
    protected array $importHeaders = ['name', 'cost', 'currency', 'expire_date', 'unit_name', 'unit_conversion_factor', 'categories'];

    protected array $importSampleData = ['Example Product', '100', 'SDG', '2026-12-31', 'Box', '10', 'Category1,Category2'];

    public function store()
    {
        Excel::import(new ProductImport, request()->file('file'));

        return back()->with('success', 'Imported successfully');
    }

    public function show(): BinaryFileResponse
    {
        return (new CsvSampleGenerator)->generate(
            'product_import_sample.csv',
            $this->importHeaders,
            $this->importSampleData
        );
    }
}
