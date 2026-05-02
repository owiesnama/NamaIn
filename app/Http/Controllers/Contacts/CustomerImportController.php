<?php

namespace App\Http\Controllers\Contacts;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessImportJob;
use App\Models\ImportLog;
use App\Services\CsvSampleGenerator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CustomerImportController extends Controller
{
    protected array $importHeaders = ['name', 'address', 'phone_number', 'credit_limit', 'opening_debit', 'opening_credit'];

    protected array $importSampleData = ['Example Customer', 'Customer Address 123', '0123456789', '5000', '1000', '0'];

    public function store()
    {
        request()->validate(['file' => 'required|file|mimes:csv,xlsx,xls']);

        $path = request()->file('file')->store('imports');

        $importLog = ImportLog::create([
            'user_id' => auth()->id(),
            'tenant_id' => auth()->user()->current_tenant_id,
            'import_type' => 'customers',
            'original_filename' => request()->file('file')->getClientOriginalName(),
            'stored_path' => $path,
        ]);

        ProcessImportJob::dispatch($importLog);

        return back()->with('flash', [
            'type' => 'import_queued',
            'import_id' => $importLog->id,
            'import_type' => 'customers',
            'message' => __('Customer import queued for processing.'),
        ]);
    }

    public function show(): BinaryFileResponse
    {
        return (new CsvSampleGenerator)->generate(
            'customers_import_sample.csv',
            $this->importHeaders,
            $this->importSampleData,
        );
    }
}
