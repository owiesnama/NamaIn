<?php

use App\Models\StockMovement;
use App\Models\Tenant;
use Database\Seeders\DashboardExampleSeeder;
use Illuminate\Support\Facades\DB;

test('the demo seeder leaves the ledger consistent with the stocks cache', function () {
    $this->seed(DashboardExampleSeeder::class);

    $tenant = Tenant::withoutGlobalScopes()->where('slug', 'demo')->firstOrFail();

    $cache = DB::table('stocks')->where('tenant_id', $tenant->id)->whereNull('deleted_at')
        ->get(['product_id', 'storage_id', 'quantity'])
        ->keyBy(fn ($r) => "{$r->product_id}:{$r->storage_id}");

    $ledger = StockMovement::withoutGlobalScopes()->where('tenant_id', $tenant->id)
        ->selectRaw('product_id, storage_id, SUM(quantity) as balance')
        ->groupBy('product_id', 'storage_id')
        ->get()
        ->keyBy(fn ($r) => "{$r->product_id}:{$r->storage_id}");

    expect($cache)->not->toBeEmpty();

    foreach ($cache as $key => $row) {
        expect((int) ($ledger[$key]->balance ?? 0))->toBe((int) $row->quantity);
    }
});
