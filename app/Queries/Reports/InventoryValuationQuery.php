<?php

namespace App\Queries\Reports;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class InventoryValuationQuery extends ReportQuery
{
    public function get(?int $storageId = null, ?int $categoryId = null): array
    {
        $key = "inventory_data_{$storageId}_{$categoryId}";

        return $this->remember(
            $key,
            fn () => $this->buildData($storageId, $categoryId),
        );
    }

    public function summary(?int $storageId = null, ?int $categoryId = null): array
    {
        $key = "inventory_summary_{$storageId}_{$categoryId}";

        return $this->remember(
            $key,
            fn () => $this->buildSummary($storageId, $categoryId),
        );
    }

    private function buildData(?int $storageId, ?int $categoryId): array
    {
        $tenantId = $this->tenantId();

        $query = DB::table('stocks')
            ->join('products', 'stocks.product_id', '=', 'products.id')
            ->join('storages', 'stocks.storage_id', '=', 'storages.id')
            ->where('products.tenant_id', $tenantId)
            ->whereNull('stocks.deleted_at')
            // Include negative (oversold) balances so they are not silently
            // dropped from valuation; they contribute negative value. Only true
            // zeros (nothing on hand) are excluded.
            ->where('stocks.quantity', '!=', 0)
            ->select(
                'products.id as product_id',
                'products.name as product_name',
                'storages.name as storage_name',
                'stocks.quantity',
                DB::raw('COALESCE(products.average_cost, products.cost, 0) / 100.0 as average_cost'),
                DB::raw('stocks.quantity * COALESCE(products.average_cost, products.cost, 0) / 100.0 as total_value'),
            );

        if ($storageId) {
            $query->where('stocks.storage_id', $storageId);
        }

        if ($categoryId) {
            $query->whereExists(function ($q) use ($categoryId) {
                $q->select(DB::raw(1))
                    ->from('categorizables')
                    ->whereColumn('categorizables.categorizable_id', 'products.id')
                    ->where('categorizables.categorizable_type', Product::class)
                    ->where('categorizables.category_id', $categoryId);
            });
        }

        return $query->orderBy('products.name')
            ->get()
            ->map(fn ($row) => [
                'product_id' => $row->product_id,
                'product_name' => $row->product_name,
                'storage_name' => $row->storage_name,
                'quantity' => (int) $row->quantity,
                'average_cost' => round((float) $row->average_cost, 2),
                'total_value' => round((float) $row->total_value, 2),
            ])
            ->all();
    }

    private function buildSummary(?int $storageId, ?int $categoryId): array
    {
        $data = $this->buildData($storageId, $categoryId);

        return [
            'total_items' => array_sum(array_column($data, 'quantity')),
            'total_value' => round(array_sum(array_column($data, 'total_value')), 2),
            'product_count' => count(array_unique(array_column($data, 'product_id'))),
        ];
    }

    protected function cacheMinutes(): int
    {
        return 15;
    }
}
