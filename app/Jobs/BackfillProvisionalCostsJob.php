<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * When a purchase establishes a real cost for a product, restate the cost of any
 * sale lines that were booked provisionally (no cost basis) and clear the flag.
 * Runs outside a tenant request, so it scopes by explicit tenant_id. Idempotent:
 * once a line is cleared it is no longer selected.
 */
class BackfillProvisionalCostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $tenantId, public int $productId) {}

    public function handle(): void
    {
        $averageCost = DB::table('products')
            ->where('id', $this->productId)
            ->value('average_cost');

        if ($averageCost === null || (int) $averageCost <= 0) {
            return;
        }

        DB::table('transactions')
            ->where('tenant_id', $this->tenantId)
            ->where('product_id', $this->productId)
            ->where('cost_provisional', true)
            ->whereNull('deleted_at')
            ->update([
                'unit_cost' => (int) $averageCost,
                'cost_provisional' => false,
                'updated_at' => now(),
            ]);
    }
}
