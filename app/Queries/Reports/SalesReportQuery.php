<?php

namespace App\Queries\Reports;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SalesReportQuery
{
    use ResolvesReportDates;

    public function getData(Carbon $from, Carbon $to, string $groupBy = 'day'): array
    {
        return Cache::remember(
            $this->cacheKey("sales_data_{$groupBy}_{$from->toDateString()}_{$to->toDateString()}"),
            $this->cacheTtl(),
            fn () => $this->buildData($from, $to, $groupBy),
        );
    }

    public function getSummary(Carbon $from, Carbon $to): array
    {
        return Cache::remember(
            $this->cacheKey("sales_summary_{$from->toDateString()}_{$to->toDateString()}"),
            $this->cacheTtl(),
            fn () => $this->buildSummary($from, $to),
        );
    }

    private function buildData(Carbon $from, Carbon $to, string $groupBy): array
    {
        $dateFormat = $this->dateFormat($groupBy);

        return Transaction::delivered()
            ->forCustomer()
            ->join('invoices', 'transactions.invoice_id', '=', 'invoices.id')
            ->whereBetween('transactions.created_at', [$from, $to])
            ->select(
                DB::raw("$dateFormat as period"),
                DB::raw('COUNT(DISTINCT invoices.id) as invoice_count'),
                DB::raw('SUM(transactions.quantity) as items_sold'),
                DB::raw('SUM(transactions.price * transactions.quantity) as revenue'),
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn ($row) => [
                'period' => $row->period,
                'invoice_count' => (int) $row->invoice_count,
                'items_sold' => (int) $row->items_sold,
                'revenue' => (float) $row->revenue,
                'average_order_value' => $row->invoice_count > 0
                    ? round((float) $row->revenue / (int) $row->invoice_count, 2)
                    : 0,
            ])
            ->all();
    }

    private function buildSummary(Carbon $from, Carbon $to): array
    {
        $result = Transaction::delivered()
            ->forCustomer()
            ->join('invoices', 'transactions.invoice_id', '=', 'invoices.id')
            ->whereBetween('transactions.created_at', [$from, $to])
            ->select(
                DB::raw('COUNT(DISTINCT invoices.id) as invoice_count'),
                DB::raw('SUM(transactions.quantity) as items_sold'),
                DB::raw('SUM(transactions.price * transactions.quantity) as revenue'),
            )
            ->first();

        return [
            'invoice_count' => (int) ($result->invoice_count ?? 0),
            'items_sold' => (int) ($result->items_sold ?? 0),
            'revenue' => (float) ($result->revenue ?? 0),
            'average_order_value' => ($result->invoice_count ?? 0) > 0
                ? round((float) $result->revenue / (int) $result->invoice_count, 2)
                : 0,
        ];
    }
}
