<?php

namespace App\Queries\Reports;

use Illuminate\Contracts\Database\Eloquent\Builder;

class SalesReportQuery extends TradeReportQuery
{
    protected function reportKey(): string
    {
        return 'sales';
    }

    protected function scopeParty(Builder $query): Builder
    {
        return $query->forCustomer();
    }

    protected function moneyKey(): string
    {
        return 'revenue';
    }

    protected function itemsKey(): string
    {
        return 'items_sold';
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function decorate(array $row): array
    {
        $row['average_order_value'] = $row['invoice_count'] > 0
            ? round($row['revenue'] / $row['invoice_count'], 2)
            : 0;

        return $row;
    }
}
