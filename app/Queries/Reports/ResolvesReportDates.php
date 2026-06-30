<?php

namespace App\Queries\Reports;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;

trait ResolvesReportDates
{
    private function cacheKey(string $key): string
    {
        $tenantId = app()->has('currentTenant') ? app('currentTenant')->id : 0;

        return "tenant_{$tenantId}_report_{$key}";
    }

    private function cacheTtl(): DateTimeInterface|int
    {
        // Only cache report results in production. Outside it (local, testing,
        // and the Cypress E2E env which runs as 'local') results must stay fresh
        // so a query right after seeding data isn't served a stale empty result.
        if (! app()->environment('production')) {
            return 0;
        }

        return now()->addMinutes(5);
    }

    private function dateFormat(string $groupBy, string $column = 'transactions.created_at'): string
    {
        return DB::dateFormat($groupBy, $column);
    }

    private function dateDiff(string $column = 'invoices.created_at'): string
    {
        return DB::dateDiff($column);
    }
}
