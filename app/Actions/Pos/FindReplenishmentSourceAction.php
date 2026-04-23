<?php

namespace App\Actions\Pos;

use App\Models\Product;
use App\Models\Storage;
use App\ValueObjects\ReplenishmentSource;

class FindReplenishmentSourceAction
{
    public function handle(Product $product, int $quantityNeeded): ?ReplenishmentSource
    {
        $warehouse = Storage::warehouses()
            ->join('stocks', function ($join) use ($product) {
                $join->on('storages.id', '=', 'stocks.storage_id')
                    ->where('stocks.product_id', $product->id);
            })
            ->where('stocks.quantity', '>=', $quantityNeeded)
            ->orderByDesc('stocks.quantity')
            ->select('storages.*', 'stocks.quantity as available_quantity')
            ->first();

        if (! $warehouse) {
            return null;
        }

        return new ReplenishmentSource(
            warehouse: $warehouse,
            availableQuantity: (int) $warehouse->available_quantity,
        );
    }
}
