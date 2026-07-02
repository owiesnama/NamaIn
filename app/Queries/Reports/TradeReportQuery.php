<?php

namespace App\Queries\Reports;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * Groups delivered trade lines by period with counts and line money.
 * Sales and purchases share the shape; children name the party side,
 * the money column, and any derived columns.
 */
abstract class TradeReportQuery extends ReportQuery
{
    /** Cache-key prefix, e.g. 'sales'. */
    abstract protected function reportKey(): string;

    /** Constrain transactions to the party side, e.g. forCustomer(). */
    abstract protected function scopeParty(Builder $query): Builder;

    /** Output key for the summed line money, e.g. 'revenue'. */
    abstract protected function moneyKey(): string;

    /** Output key for the summed quantities, e.g. 'items_sold'. */
    abstract protected function itemsKey(): string;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get(Carbon $from, Carbon $to, string $groupBy = 'day'): array
    {
        return $this->remember(
            "{$this->reportKey()}_data_{$groupBy}_{$from->toDateString()}_{$to->toDateString()}",
            fn () => $this->buildData($from, $to, $groupBy),
        );
    }

    /**
     * @return array<string, int|float>
     */
    public function summary(Carbon $from, Carbon $to): array
    {
        return $this->remember(
            "{$this->reportKey()}_summary_{$from->toDateString()}_{$to->toDateString()}",
            fn () => $this->buildSummary($from, $to),
        );
    }

    /**
     * Hook for report-specific derived columns (e.g. average order value).
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function decorate(array $row): array
    {
        return $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildData(Carbon $from, Carbon $to, string $groupBy): array
    {
        $dateFormat = $this->dateFormat($groupBy);

        return $this->baseQuery($from, $to)
            ->select(
                DB::raw("$dateFormat as period"),
                ...$this->aggregateColumns(),
            )
            ->groupBy('period')
            ->orderBy('period')
            ->toBase()
            ->get()
            ->map(fn ($row) => $this->decorate([
                'period' => $row->period,
                'invoice_count' => (int) $row->invoice_count,
                $this->itemsKey() => (int) $row->items,
                $this->moneyKey() => (float) $row->money,
            ]))
            ->all();
    }

    /**
     * @return array<string, int|float>
     */
    private function buildSummary(Carbon $from, Carbon $to): array
    {
        $result = $this->baseQuery($from, $to)
            ->select(...$this->aggregateColumns())
            ->toBase()
            ->first();

        return $this->decorate([
            'invoice_count' => (int) ($result->invoice_count ?? 0),
            $this->itemsKey() => (int) ($result->items ?? 0),
            $this->moneyKey() => (float) ($result->money ?? 0),
        ]);
    }

    private function baseQuery(Carbon $from, Carbon $to): Builder
    {
        return $this->scopeParty(Transaction::delivered())
            ->join('invoices', 'transactions.invoice_id', '=', 'invoices.id')
            ->whereBetween('transactions.created_at', [$from, $to]);
    }

    /**
     * @return array<int, Expression>
     */
    private function aggregateColumns(): array
    {
        return [
            DB::raw('COUNT(DISTINCT invoices.id) as invoice_count'),
            DB::raw('SUM(transactions.quantity) as items'),
            DB::raw('SUM('.Transaction::lineRevenueSql('transactions').') / 100.0 as money'),
        ];
    }
}
