<?php

namespace App\Jobs;

use App\Events\ImportStatusUpdated;
use App\Imports\CustomerImport;
use App\Imports\ProductImport;
use App\Imports\QuickBooksCustomerImport;
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

    /** @var array<string, array<string, class-string>> */
    private const IMPORT_MAP = [
        'products' => ['default' => ProductImport::class],
        'suppliers' => ['default' => SupplierImport::class],
        'customers' => [
            'default' => CustomerImport::class,
            'quickbooks' => QuickBooksCustomerImport::class,
        ],
    ];

    public function __construct(public ImportLog $importLog)
    {
        $this->onQueue('imports');
    }

    public function handle(): void
    {
        $this->bindTenantContext();

        $this->importLog->markProcessing();
        ImportStatusUpdated::dispatch($this->importLog);

        $template = $this->importLog->template ?? 'default';
        $importClass = self::IMPORT_MAP[$this->importLog->import_type][$template] ?? null;

        if (! $importClass) {
            $this->importLog->markFailed("Unknown import type or template: {$this->importLog->import_type}/{$template}");
            ImportStatusUpdated::dispatch($this->importLog);

            return;
        }

        $import = new $importClass;

        Excel::import($import, storage_path("app/{$this->importLog->stored_path}"));

        $rowsImported = method_exists($import, 'getRowCount')
            ? $import->getRowCount()
            : 0;

        $this->storeFailures($import);

        $this->importLog->markCompleted($rowsImported);
        ImportStatusUpdated::dispatch($this->importLog);

        @unlink(storage_path("app/{$this->importLog->stored_path}"));
    }

    public function failed(\Throwable $exception): void
    {
        $this->importLog->markFailed($exception->getMessage());
        ImportStatusUpdated::dispatch($this->importLog);
    }

    private function storeFailures(object $import): void
    {
        if (! method_exists($import, 'failures')) {
            return;
        }

        $failures = $import->failures();

        if (empty($failures)) {
            return;
        }

        $this->importLog->update([
            'failures' => collect($failures)->map(fn ($failure) => [
                'row' => $failure->row(),
                'attribute' => $failure->attribute(),
                'errors' => $failure->errors(),
            ])->toArray(),
        ]);
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
