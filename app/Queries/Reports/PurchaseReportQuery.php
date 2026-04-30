<?php

namespace App\Queries\Reports;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PurchaseReportQuery
{
    use ResolvesReportDates;

    public function getData(Carbon $from, Carbon $to, string $groupBy = 'day'): array
    {
        return Cache::remember(
            $this->cacheKey("purchase_data_{$groupBy}_{$from->toDateString()}_{$to->toDateString()}"),
            $this->cacheTtl(),
            fn () => $this->buildData($from, $to, $groupBy),
        );
    }

    public function getSummary(Carbon $from, Carbon $to): array
    {
        return Cache::remember(
            $this->cacheKey("purchase_summary_{$from->toDateString()}_{$to->toDateString()}"),
            $this->cacheTtl(),
            fn () => $this->buildSummary($from, $to),
        );
    }

    private function buildData(Carbon $from, Carbon $to, string $groupBy): array
    {
        $dateFormat = $this->dateFormat($groupBy);

        return Transaction::delivered()
            ->forSupplier()
            ->join('invoices', 'transactions.invoice_id', '=', 'invoices.id')
            ->whereBetween('transactions.created_at', [$from, $to])
            ->select(
                DB::raw("$dateFormat as period"),
                DB::raw('COUNT(DISTINCT invoices.id) as invoice_count'),
                DB::raw('SUM(transactions.base_quantity) as items_purchased'),
                DB::raw('SUM(transactions.price * transactions.base_quantity) as total_cost'),
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn ($row) => [
                'period' => $row->period,
                'invoice_count' => (int) $row->invoice_count,
                'items_purchased' => (int) $row->items_purchased,
                'total_cost' => (float) $row->total_cost,
            ])
            ->all();
    }

    private function buildSummary(Carbon $from, Carbon $to): array
    {
        $result = Transaction::delivered()
            ->forSupplier()
            ->join('invoices', 'transactions.invoice_id', '=', 'invoices.id')
            ->whereBetween('transactions.created_at', [$from, $to])
            ->select(
                DB::raw('COUNT(DISTINCT invoices.id) as invoice_count'),
                DB::raw('SUM(transactions.base_quantity) as items_purchased'),
                DB::raw('SUM(transactions.price * transactions.base_quantity) as total_cost'),
            )
            ->first();

        return [
            'invoice_count' => (int) ($result->invoice_count ?? 0),
            'items_purchased' => (int) ($result->items_purchased ?? 0),
            'total_cost' => (float) ($result->total_cost ?? 0),
        ];
    }
}
