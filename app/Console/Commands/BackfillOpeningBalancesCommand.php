<?php

namespace App\Console\Commands;

use App\Enums\MovementType;
use App\Models\StockMovement;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Seeds an opening-balance movement per (tenant, product, storage) so the
 * ledger reconstructs the current cache exactly:
 *
 *     opening = stocks.quantity - SUM(stock_movements.quantity)
 *
 * The delta absorbs both historical drift and seeder bypasses. Idempotent:
 * once the ledger equals the cache, a re-run finds nothing to do. Run
 * `stock:reconcile` (or this command's --dry-run) first as a pre-flight gate.
 */
class BackfillOpeningBalancesCommand extends Command
{
    protected $signature = 'stock:backfill-opening-balances {--dry-run : Report what would be created without writing} {--tenant= : Limit to a single tenant (id or slug)}';

    protected $description = 'Insert opening-balance movements so the ledger reconstructs the current stock cache.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $previousTenant = app()->has('currentTenant') ? app('currentTenant') : null;
        $created = 0;

        try {
            foreach ($this->tenants() as $tenant) {
                app()->instance('currentTenant', $tenant);
                $created += $this->backfillTenant($tenant, $dryRun);
            }
        } finally {
            if ($previousTenant) {
                app()->instance('currentTenant', $previousTenant);
            } else {
                app()->forgetInstance('currentTenant');
            }
        }

        $verb = $dryRun ? 'would be created' : 'created';
        $this->info("{$created} opening-balance movement(s) {$verb}.");

        return self::SUCCESS;
    }

    private function tenants()
    {
        $filter = $this->option('tenant');

        return Tenant::withoutGlobalScopes()
            ->when($filter, fn ($query) => $query->where('id', $filter)->orWhere('slug', $filter))
            ->get();
    }

    private function backfillTenant(Tenant $tenant, bool $dryRun): int
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

        $created = 0;

        foreach ($cache->keys()->merge($ledger->keys())->unique() as $key) {
            [$productId, $storageId] = array_map('intval', explode(':', $key));
            $cacheQty = (int) ($cache[$key]->quantity ?? 0);
            $ledgerQty = (int) ($ledger[$key]->balance ?? 0);
            $opening = $cacheQty - $ledgerQty;

            if ($opening === 0) {
                continue;
            }

            $created++;

            if ($dryRun) {
                $this->line("[{$tenant->slug}] product {$productId} / storage {$storageId}: opening {$opening} (cache {$cacheQty}, ledger {$ledgerQty})");

                continue;
            }

            StockMovement::create([
                'tenant_id' => $tenant->id,
                'storage_id' => $storageId,
                'product_id' => $productId,
                'user_id' => null,
                'reason' => 'opening_balance',
                'movement_type' => MovementType::OpeningBalance,
                'quantity' => $opening,
                'quantity_before' => $ledgerQty,
                'quantity_after' => $cacheQty,
            ]);
        }

        return $created;
    }
}
