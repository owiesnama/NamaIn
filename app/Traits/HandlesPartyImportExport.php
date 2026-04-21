<?php

namespace App\Traits;

use App\Services\CsvSampleGenerator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

trait HandlesPartyImportExport
{
    public function import()
    {
        Excel::import(new $this->importClass, request()->file('file'));

        return back()->with('success', 'Imported successfully');
    }

    public function importSample(): BinaryFileResponse
    {
        $filename = Str::plural(Str::snake(class_basename($this->model))).'_import_sample.csv';

        return (new CsvSampleGenerator)->generate($filename, $this->importHeaders, $this->importSampleData);
    }

    public function export()
    {
        return Excel::download(new $this->exportClass, Str::plural(Str::snake(class_basename($this->model))).'.xlsx');
    }
}
