<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\TreasuryAccount;
use App\Models\Unit;
use App\Services\ArabicEncodingNormalizer;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixProductEncodingCommand extends Command
{
    public function __construct(private readonly ArabicEncodingNormalizer $normalizer)
    {
        parent::__construct();
    }

    protected $signature = 'data:fix-encoding
                            {--tenant= : Specific tenant slug to fix}
                            {--dry-run : Preview changes without saving}
                            {--backup : Create a backup table before making changes}';

    protected $description = 'Fix garbled Arabic text caused by Windows-1256 → UTF-8 mojibake';

    /**
     * Models and the text fields to scan/fix on each.
     *
     * @var array<class-string<Model>, array<string>>
     */
    private array $targets = [
        Product::class         => ['name'],
        Category::class        => ['name'],
        Customer::class        => ['name', 'address'],
        Supplier::class        => ['name', 'address'],
        Storage::class         => ['name', 'address'],
        TreasuryAccount::class => ['name'],
        Unit::class            => ['name'],
        Expense::class         => ['title', 'notes'],
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $backup = $this->option('backup');
        $tenantSlug = $this->option('tenant');

        $tenants = $this->resolveTenants($tenantSlug);
        if ($tenants === null) {
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('DRY-RUN mode — no changes will be saved.');
            $this->newLine();
        }

        $grandTotal = 0;

        foreach ($tenants as $tenant) {
            $this->line("<fg=cyan>Tenant: {$tenant->name} ({$tenant->slug})</>");

            app()->instance('currentTenant', $tenant);

            if ($backup && ! $dryRun) {
                $this->createBackup($tenant);
            }

            $tenantTotal = 0;

            foreach ($this->targets as $modelClass => $fields) {
                $tenantTotal += $this->processModel($modelClass, $fields, $dryRun);
            }

            $status = $dryRun ? 'would be fixed' : 'fixed';
            $this->line("  → {$tenantTotal} values {$status} in {$tenant->name}");
            $this->newLine();

            $grandTotal += $tenantTotal;

            app()->forgetInstance('currentTenant');
        }

        $this->info("Done. Grand total: {$grandTotal} values " . ($dryRun ? 'would be fixed.' : 'fixed.'));

        if ($dryRun) {
            $this->warn('Remove --dry-run to apply changes.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string>        $fields
     */
    private function processModel(string $modelClass, array $fields, bool $dryRun): int
    {
        $table = (new $modelClass)->getTable();
        $shortName = class_basename($modelClass);
        $fixed = 0;

        $modelClass::query()
            ->select(array_merge(['id'], $fields))
            ->chunkById(500, function (Collection $rows) use ($table, $fields, $dryRun, &$fixed, $shortName) {
                DB::transaction(function () use ($rows, $table, $fields, $dryRun, &$fixed, $shortName) {
                    foreach ($rows as $row) {
                        $updates = [];

                        foreach ($fields as $field) {
                            $value = $row->{$field};
                            if (! is_string($value) || $value === '') {
                                continue;
                            }

                            if (! $this->normalizer->isGarbled($value)) {
                                continue;
                            }

                            $fixed_value = $this->normalizer->normalize($value);

                            if ($fixed_value === $value) {
                                continue;
                            }

                            $this->line("  [{$shortName}#{$row->id}] {$field}: {$value} → {$fixed_value}");
                            $updates[$field] = $fixed_value;
                        }

                        if (! empty($updates)) {
                            if (! $dryRun) {
                                DB::table($table)->where('id', $row->id)->update($updates);

                                Log::channel('single')->info('fix-encoding', [
                                    'table'  => $table,
                                    'id'     => $row->id,
                                    'fields' => array_keys($updates),
                                ]);
                            }

                            $fixed++;
                        }
                    }
                });
            });

        return $fixed;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Tenant>|null
     */
    private function resolveTenants(?string $slug): ?Collection
    {
        if ($slug === null) {
            return Tenant::all();
        }

        $tenant = Tenant::where('slug', $slug)->first();

        if (! $tenant) {
            $this->error("Tenant '{$slug}' not found.");

            return null;
        }

        return collect([$tenant]);
    }

    /**
     * Snapshot the current state of every affected table into `<table>_encoding_backup_<tenant_id>`.
     * Only creates it once — safe to call on re-runs.
     */
    private function createBackup(Tenant $tenant): void
    {
        foreach ($this->targets as $modelClass => $_) {
            $table = (new $modelClass)->getTable();
            $backupTable = "{$table}_enc_bak_{$tenant->id}";

            if (DB::getSchemaBuilder()->hasTable($backupTable)) {
                $this->comment("  Backup '{$backupTable}' already exists — skipping.");
                continue;
            }

            DB::statement("CREATE TABLE {$backupTable} AS SELECT * FROM {$table} WHERE tenant_id = ?", [$tenant->id]);
            $this->info("  Backup created: {$backupTable}");
        }
    }
}
