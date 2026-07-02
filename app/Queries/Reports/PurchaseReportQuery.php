<?php

namespace App\Queries\Reports;

use Illuminate\Contracts\Database\Eloquent\Builder;

class PurchaseReportQuery extends TradeReportQuery
{
    protected function reportKey(): string
    {
        return 'purchase';
    }

    protected function scopeParty(Builder $query): Builder
    {
        return $query->forSupplier();
    }

    protected function moneyKey(): string
    {
        return 'total_cost';
    }

    protected function itemsKey(): string
    {
        return 'items_purchased';
    }
}
