<?php

namespace App\Actions;

use App\Jobs\GenerateExportJob;
use App\Models\ExportLog;

class RequestExportAction
{
    public function execute(string $exportKey, string $format, array $filters = []): ExportLog
    {
        $exportLog = ExportLog::create([
            'user_id' => auth()->id(),
            'tenant_id' => auth()->user()->current_tenant_id,
            'export_key' => $exportKey,
            'format' => $format,
            'filters' => $filters,
        ]);

        GenerateExportJob::dispatch($exportLog);

        return $exportLog;
    }
}
