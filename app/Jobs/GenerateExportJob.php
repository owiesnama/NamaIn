<?php

namespace App\Jobs;

use App\Events\ExportStatusUpdated;
use App\Models\ExportLog;
use App\Models\Preference;
use App\Models\Tenant;
use App\Services\ExportRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

class GenerateExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public array $backoff = [10, 30, 60];

    public function __construct(public ExportLog $exportLog) {}

    public function handle(): void
    {
        $this->bindTenantContext();

        $this->exportLog->markProcessing();
        ExportStatusUpdated::dispatch($this->exportLog);

        $exportClass = ExportRegistry::resolve($this->exportLog->export_key);

        if (! $exportClass) {
            $this->exportLog->markFailed("Unknown export key: {$this->exportLog->export_key}");
            ExportStatusUpdated::dispatch($this->exportLog);

            return;
        }

        $filename = $this->generateFilename();
        $path = "exports/{$this->exportLog->tenant_id}/{$filename}";

        $export = app()->make($exportClass, ['filters' => $this->exportLog->filters ?? []]);

        $data = method_exists($export, 'array') ? $export->array() : null;
        $collection = method_exists($export, 'collection') ? $export->collection() : null;

        $isEmpty = ($data !== null && count($data) === 0)
            || ($collection !== null && $collection->isEmpty());

        if ($isEmpty) {
            $this->exportLog->markFailed(__('No data to export for the selected filters.'));
            ExportStatusUpdated::dispatch($this->exportLog);

            return;
        }

        Excel::store($export, $path, 'local');

        $this->exportLog->markCompleted($path, $filename);
        ExportStatusUpdated::dispatch($this->exportLog);
    }

    public function failed(\Throwable $exception): void
    {
        $this->exportLog->markFailed($exception->getMessage());
        ExportStatusUpdated::dispatch($this->exportLog);
    }

    private function bindTenantContext(): void
    {
        $tenant = Tenant::find($this->exportLog->tenant_id);

        if ($tenant) {
            app()->instance('currentTenant', $tenant);

            $preferences = Preference::where('tenant_id', $tenant->id)->pluck('value', 'key')->toArray();
            app()->setLocale($preferences['language'] ?? config('app.locale'));
        }
    }

    private function generateFilename(): string
    {
        $key = str_replace('-', '_', $this->exportLog->export_key);
        $date = now()->format('Y_m_d_His');

        return "{$key}_{$date}.{$this->exportLog->format}";
    }
}
