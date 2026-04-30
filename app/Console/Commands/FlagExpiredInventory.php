<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FlagExpiredInventory extends Command
{
    protected $signature = 'inventory:flag-expired';

    protected $description = 'Flag products that have passed their expiry date and still have stock';

    public function handle(): void
    {
        $stockSubquery = '(SELECT COALESCE(SUM(quantity), 0) FROM stocks WHERE stocks.product_id = products.id AND stocks.deleted_at IS NULL)';

        $expired = Product::withoutGlobalScopes()
            ->whereNotNull('expire_date')
            ->where('expire_date', '<=', now())
            ->whereRaw("$stockSubquery > 0")
            ->select('products.*', DB::raw("$stockSubquery as stock_on_hand"))
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired products with stock found.');

            return;
        }

        $this->warn("Found {$expired->count()} expired product(s) with stock:");

        foreach ($expired as $product) {
            $this->line("  - {$product->name} (expired {$product->expire_date->diffForHumans()}, {$product->stock_on_hand} units remaining)");
        }
    }
}
