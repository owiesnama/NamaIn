<?php

namespace App\Queries\Reports;

use App\Models\Invoice;
use App\Models\Supplier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SupplierAgingQuery
{
    use ResolvesReportDates;

    public function getData(?int $supplierId = null): array
    {
        return Cache::remember(
            $this->cacheKey("supplier_aging_data_{$supplierId}"),
            $this->cacheTtl(),
            fn () => $this->buildData($supplierId),
        );
    }

    public function getSummary(?int $supplierId = null): array
    {
        return Cache::remember(
            $this->cacheKey("supplier_aging_summary_{$supplierId}"),
            $this->cacheTtl(),
            fn () => $this->buildSummary($supplierId),
        );
    }

    private function buildData(?int $supplierId): array
    {
        $dateDiff = $this->dateDiff();

        $query = Invoice::forSupplier()
            ->outstanding()
            ->join('suppliers', function ($join) {
                $join->on('invoices.invocable_id', '=', 'suppliers.id')
                    ->where('invoices.invocable_type', Supplier::class);
            })
            ->select(
                'suppliers.id as supplier_id',
                'suppliers.name as supplier_name',
                DB::raw("SUM(CASE WHEN $dateDiff BETWEEN 0 AND 30 THEN (invoices.total - invoices.discount) - invoices.paid_amount ELSE 0 END) as bucket_0_30"),
                DB::raw("SUM(CASE WHEN $dateDiff BETWEEN 31 AND 60 THEN (invoices.total - invoices.discount) - invoices.paid_amount ELSE 0 END) as bucket_31_60"),
                DB::raw("SUM(CASE WHEN $dateDiff BETWEEN 61 AND 90 THEN (invoices.total - invoices.discount) - invoices.paid_amount ELSE 0 END) as bucket_61_90"),
                DB::raw("SUM(CASE WHEN $dateDiff > 90 THEN (invoices.total - invoices.discount) - invoices.paid_amount ELSE 0 END) as bucket_90_plus"),
                DB::raw('SUM((invoices.total - invoices.discount) - invoices.paid_amount) as total'),
            )
            ->groupBy('suppliers.id', 'suppliers.name')
            ->orderByDesc('total');

        if ($supplierId) {
            $query->where('suppliers.id', $supplierId);
        }

        return $query->get()
            ->map(fn ($row) => [
                'supplier_id' => $row->supplier_id,
                'supplier_name' => $row->supplier_name,
                'bucket_0_30' => round((float) $row->bucket_0_30, 2),
                'bucket_31_60' => round((float) $row->bucket_31_60, 2),
                'bucket_61_90' => round((float) $row->bucket_61_90, 2),
                'bucket_90_plus' => round((float) $row->bucket_90_plus, 2),
                'total' => round((float) $row->total, 2),
            ])
            ->all();
    }

    private function buildSummary(?int $supplierId): array
    {
        $data = $this->buildData($supplierId);

        return [
            'supplier_count' => count($data),
            'bucket_0_30' => round(array_sum(array_column($data, 'bucket_0_30')), 2),
            'bucket_31_60' => round(array_sum(array_column($data, 'bucket_31_60')), 2),
            'bucket_61_90' => round(array_sum(array_column($data, 'bucket_61_90')), 2),
            'bucket_90_plus' => round(array_sum(array_column($data, 'bucket_90_plus')), 2),
            'total' => round(array_sum(array_column($data, 'total')), 2),
        ];
    }
}
