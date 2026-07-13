<?php

namespace App\Queries\Reports;

use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Products currently below zero, how long they have been negative, and the
 * movements that drove them there — so someone can decide per case (missed
 * purchase entry, shrinkage, or counting error) and resolve it with the right
 * movement. Unlike InventoryValuationQuery this intentionally surfaces
 * negatives instead of filtering them out.
 */
class NegativeStockQuery extends ReportQuery
{
    public function get(?int $storageId = null): array
    {
        return $this->negativeRows($storageId)
            ->map(fn ($row) => $this->describe($row))
            ->all();
    }

    public function summary(?int $storageId = null): array
    {
        $rows = $this->negativeRows($storageId);

        return [
            'product_count' => $rows->pluck('product_id')->unique()->count(),
            'line_count' => $rows->count(),
            'units_short' => (int) $rows->sum(fn ($row) => abs((int) $row->quantity)),
        ];
    }

    private function negativeRows(?int $storageId)
    {
        return DB::table('stocks')
            ->join('products', 'stocks.product_id', '=', 'products.id')
            ->join('storages', 'stocks.storage_id', '=', 'storages.id')
            ->where('products.tenant_id', $this->tenantId())
            ->whereNull('stocks.deleted_at')
            ->where('stocks.quantity', '<', 0)
            ->when($storageId, fn ($query) => $query->where('stocks.storage_id', $storageId))
            ->orderBy('stocks.quantity')
            ->get([
                'products.id as product_id',
                'products.name as product_name',
                'storages.id as storage_id',
                'storages.name as storage_name',
                'stocks.quantity',
            ]);
    }

    private function describe(object $row): array
    {
        // The current negative streak began at the last movement that crossed
        // the balance from non-negative to negative.
        $crossing = StockMovement::query()
            ->where('product_id', $row->product_id)
            ->where('storage_id', $row->storage_id)
            ->where('quantity_before', '>=', 0)
            ->where('quantity_after', '<', 0)
            ->latest('created_at')
            ->latest('id')
            ->first();

        $negativeSince = $crossing?->created_at;

        return [
            'product_id' => (int) $row->product_id,
            'product_name' => $row->product_name,
            'storage_id' => (int) $row->storage_id,
            'storage_name' => $row->storage_name,
            'quantity' => (int) $row->quantity,
            'negative_since' => $negativeSince?->toDateString(),
            'days_negative' => $negativeSince ? (int) $negativeSince->startOfDay()->diffInDays(Carbon::now()->startOfDay()) : null,
            'movements' => $this->drivingMovements($row, $negativeSince),
        ];
    }

    private function drivingMovements(object $row, ?Carbon $since): array
    {
        return StockMovement::query()
            ->where('product_id', $row->product_id)
            ->where('storage_id', $row->storage_id)
            ->when($since, fn ($query) => $query->where('created_at', '>=', $since))
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(20)
            ->get()
            ->map(fn (StockMovement $movement) => [
                'type' => $movement->movement_type?->value,
                'type_label' => $movement->movement_type ? __($movement->movement_type->label()) : $movement->reason,
                'quantity' => (int) $movement->quantity,
                'quantity_after' => (int) $movement->quantity_after,
                'created_at' => $movement->created_at?->toDateTimeString(),
            ])
            ->all();
    }
}
