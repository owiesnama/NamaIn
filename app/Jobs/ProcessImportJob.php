<?php

namespace App\Jobs;

use App\Events\ImportStatusUpdated;
use App\Imports\CustomerImport;
use App\Imports\ProductImport;
use App\Imports\SupplierImport;
use App\Models\ImportLog;
use App\Models\Preference;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    /** @var array<string, class-string> */
    private const IMPORT_MAP = [
        'products' => ProductImport::class,
        'suppliers' => SupplierImport::class,
        'customers' => CustomerImport::class,
    ];

    public function __construct(public ImportLog $importLog) {}

    public function handle(): void
    {
        $this->bindTenantContext();

        $this->importLog->markProcessing();
        ImportStatusUpdated::dispatch($this->importLog);

        $importClass = self::IMPORT_MAP[$this->importLog->import_type] ?? null;

        if (! $importClass) {
            $this->importLog->markFailed("Unknown import type: {$this->importLog->import_type}");
            ImportStatusUpdated::dispatch($this->importLog);

            return;
        }

        $import = new $importClass;

        Excel::import($import, storage_path("app/{$this->importLog->stored_path}"));

        $rowsImported = method_exists($import, 'getRowCount')
            ? $import->getRowCount()
            : 0;

        $this->importLog->markCompleted($rowsImported);
        ImportStatusUpdated::dispatch($this->importLog);

        @unlink(storage_path("app/{$this->importLog->stored_path}"));
    }

    public function failed(\Throwable $exception): void
    {
        $this->importLog->markFailed($exception->getMessage());
        ImportStatusUpdated::dispatch($this->importLog);
    }

    private function bindTenantContext(): void
    {
        $tenant = Tenant::find($this->importLog->tenant_id);

        if ($tenant) {
            app()->instance('currentTenant', $tenant);

            $preferences = Preference::where('tenant_id', $tenant->id)->pluck('value', 'key')->toArray();
            app()->setLocale($preferences['language'] ?? config('app.locale'));
        }
    }
}
