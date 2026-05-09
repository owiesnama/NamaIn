<?php

namespace App\Http\Controllers\Concerns;

use App\Jobs\ProcessImportJob;
use App\Models\ImportLog;
use App\Services\Utils\CsvSampleGenerator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

trait HandlesImport
{
    abstract protected function importType(): string;

    abstract protected function importHeaders(): array;

    abstract protected function importSampleData(): array;

    protected function allowedTemplates(): array
    {
        return ['default'];
    }

    protected function sampleFilename(): string
    {
        return $this->importType().'_import_sample.csv';
    }

    public function store()
    {
        request()->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls',
            'template' => ['sometimes', 'string', 'in:'.implode(',', $this->allowedTemplates())],
        ]);

        $importLog = ImportLog::create([
            'user_id' => auth()->id(),
            'tenant_id' => auth()->user()->current_tenant_id,
            'import_type' => $this->importType(),
            'template' => request('template', 'default'),
            'original_filename' => request()->file('file')->getClientOriginalName(),
            'stored_path' => request()->file('file')->store('imports'),
        ]);

        ProcessImportJob::dispatch($importLog);

        $type = $this->importType();

        return back()->with('flash', [
            'type' => 'import_queued',
            'import_id' => $importLog->id,
            'import_type' => $type,
            'message' => __(ucfirst($type).' import queued for processing.'),
        ]);
    }

    public function show(): BinaryFileResponse
    {
        return (new CsvSampleGenerator)->generate(
            $this->sampleFilename(),
            $this->importHeaders(),
            $this->importSampleData(),
        );
    }
}
