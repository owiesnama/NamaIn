<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReconcileStockCommand extends Command
{
    protected $signature = 'stock:reconcile {--json : Emit machine-readable JSON} {--tenant= : Limit to a single tenant (id or slug)}';

    protected $description = 'Report drift between the stocks.quantity cache and the stock-movement ledger. Read-only.';

    public function handle(): int
    {
        $previousTenant = app()->has('currentTenant') ? app('currentTenant') : null;

        $drifts = [];

        try {
            foreach ($this->tenants() as $tenant) {
                app()->instance('currentTenant', $tenant);

                foreach ($this->driftsForTenant($tenant) as $drift) {
                    $drifts[] = $drift;
                }
            }
        } finally {
            if ($previousTenant) {
                app()->instance('currentTenant', $previousTenant);
            } else {
                app()->forgetInstance('currentTenant');
            }
        }

        $this->option('json')
            ? $this->line(json_encode($drifts, JSON_PRETTY_PRINT))
            : $this->renderTable($drifts);

        return empty($drifts) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function tenants()
    {
        $filter = $this->option('tenant');

        return Tenant::withoutGlobalScopes()
            ->when($filter, fn ($query) => $query->where('id', $filter)->orWhere('slug', $filter))
            ->get();
    }

    /**
     * Compare the cache against the ledger for one tenant.
     *
     * @return array<int, array{tenant: string, product_id: int, storage_id: int, cache: int, ledger: int, drift: int}>
     */
    private function driftsForTenant(Tenant $tenant): array
    {
        $cache = DB::table('stocks')
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->get(['product_id', 'storage_id', 'quantity'])
            ->keyBy(fn ($row) => "{$row->product_id}:{$row->storage_id}");

        $ledger = DB::table('stock_movements')
            ->where('tenant_id', $tenant->id)
            ->selectRaw('product_id, storage_id, SUM(quantity) as balance')
            ->groupBy('product_id', 'storage_id')
            ->get()
            ->keyBy(fn ($row) => "{$row->product_id}:{$row->storage_id}");

        $drifts = [];

        foreach ($cache->keys()->merge($ledger->keys())->unique() as $key) {
            [$productId, $storageId] = array_map('intval', explode(':', $key));
            $cacheQty = (int) ($cache[$key]->quantity ?? 0);
            $ledgerQty = (int) ($ledger[$key]->balance ?? 0);

            if ($cacheQty === $ledgerQty) {
                continue;
            }

            $drifts[] = [
                'tenant' => $tenant->slug,
                'product_id' => $productId,
                'storage_id' => $storageId,
                'cache' => $cacheQty,
                'ledger' => $ledgerQty,
                'drift' => $cacheQty - $ledgerQty,
            ];
        }

        return $drifts;
    }

    /**
     * @param  array<int, array{tenant: string, product_id: int, storage_id: int, cache: int, ledger: int, drift: int}>  $drifts
     */
    private function renderTable(array $drifts): void
    {
        if (empty($drifts)) {
            $this->info('No drift: the cache and the ledger agree for every product and storage.');

            return;
        }

        $this->warn(count($drifts).' drifting (product, storage) pair(s) found:');
        $this->table(
            ['Tenant', 'Product', 'Storage', 'Cache', 'Ledger', 'Drift'],
            array_map(fn ($d) => [$d['tenant'], $d['product_id'], $d['storage_id'], $d['cache'], $d['ledger'], $d['drift']], $drifts),
        );
    }
}
