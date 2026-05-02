<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessImportJob;
use App\Models\ImportLog;
use App\Services\CsvSampleGenerator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductImportController extends Controller
{
    protected array $importHeaders = ['name', 'cost', 'currency', 'expire_date', 'unit_name', 'unit_conversion_factor', 'categories'];

    protected array $importSampleData = ['Example Product', '100', 'SDG', '2026-12-31', 'Box', '10', 'Category1,Category2'];

    public function store()
    {
        request()->validate(['file' => 'required|file|mimes:csv,xlsx,xls']);

        $path = request()->file('file')->store('imports');

        $importLog = ImportLog::create([
            'user_id' => auth()->id(),
            'tenant_id' => auth()->user()->current_tenant_id,
            'import_type' => 'products',
            'original_filename' => request()->file('file')->getClientOriginalName(),
            'stored_path' => $path,
        ]);

        ProcessImportJob::dispatch($importLog);

        return back()->with('flash', [
            'type' => 'import_queued',
            'import_id' => $importLog->id,
            'import_type' => 'products',
            'message' => __('Product import queued for processing.'),
        ]);
    }

    public function show(): BinaryFileResponse
    {
        return (new CsvSampleGenerator)->generate(
            'product_import_sample.csv',
            $this->importHeaders,
            $this->importSampleData,
        );
    }
}
