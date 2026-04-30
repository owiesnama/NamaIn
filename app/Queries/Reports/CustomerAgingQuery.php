<?php

namespace App\Queries\Reports;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CustomerAgingQuery
{
    use ResolvesReportDates;

    public function getData(?int $customerId = null): array
    {
        return Cache::remember(
            $this->cacheKey("customer_aging_data_{$customerId}"),
            $this->cacheTtl(),
            fn () => $this->buildData($customerId),
        );
    }

    public function getSummary(?int $customerId = null): array
    {
        return Cache::remember(
            $this->cacheKey("customer_aging_summary_{$customerId}"),
            $this->cacheTtl(),
            fn () => $this->buildSummary($customerId),
        );
    }

    private function buildData(?int $customerId): array
    {
        $dateDiff = $this->dateDiff();

        $query = Invoice::forCustomer()
            ->outstanding()
            ->join('customers', function ($join) {
                $join->on('invoices.invocable_id', '=', 'customers.id')
                    ->where('invoices.invocable_type', Customer::class);
            })
            ->select(
                'customers.id as customer_id',
                'customers.name as customer_name',
                DB::raw("SUM(CASE WHEN $dateDiff BETWEEN 0 AND 30 THEN (invoices.total - invoices.discount) - invoices.paid_amount ELSE 0 END) as bucket_0_30"),
                DB::raw("SUM(CASE WHEN $dateDiff BETWEEN 31 AND 60 THEN (invoices.total - invoices.discount) - invoices.paid_amount ELSE 0 END) as bucket_31_60"),
                DB::raw("SUM(CASE WHEN $dateDiff BETWEEN 61 AND 90 THEN (invoices.total - invoices.discount) - invoices.paid_amount ELSE 0 END) as bucket_61_90"),
                DB::raw("SUM(CASE WHEN $dateDiff > 90 THEN (invoices.total - invoices.discount) - invoices.paid_amount ELSE 0 END) as bucket_90_plus"),
                DB::raw('SUM((invoices.total - invoices.discount) - invoices.paid_amount) as total'),
            )
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('total');

        if ($customerId) {
            $query->where('customers.id', $customerId);
        }

        return $query->get()
            ->map(fn ($row) => [
                'customer_id' => $row->customer_id,
                'customer_name' => $row->customer_name,
                'bucket_0_30' => round((float) $row->bucket_0_30, 2),
                'bucket_31_60' => round((float) $row->bucket_31_60, 2),
                'bucket_61_90' => round((float) $row->bucket_61_90, 2),
                'bucket_90_plus' => round((float) $row->bucket_90_plus, 2),
                'total' => round((float) $row->total, 2),
            ])
            ->all();
    }

    private function buildSummary(?int $customerId): array
    {
        $data = $this->buildData($customerId);

        return [
            'customer_count' => count($data),
            'bucket_0_30' => round(array_sum(array_column($data, 'bucket_0_30')), 2),
            'bucket_31_60' => round(array_sum(array_column($data, 'bucket_31_60')), 2),
            'bucket_61_90' => round(array_sum(array_column($data, 'bucket_61_90')), 2),
            'bucket_90_plus' => round(array_sum(array_column($data, 'bucket_90_plus')), 2),
            'total' => round(array_sum(array_column($data, 'total')), 2),
        ];
    }
}
