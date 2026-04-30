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
        if (app()->environment('testing')) {
            return 0;
        }

        return now()->addMinutes(5);
    }

    private function dateFormat(string $groupBy, string $column = 'transactions.created_at'): string
    {
        $driver = DB::getDriverName();

        return match ($groupBy) {
            'week' => match ($driver) {
                'sqlite' => "strftime('%Y-W%W', $column)",
                'pgsql' => "TO_CHAR($column, 'IYYY-\"W\"IW')",
                default => "DATE_FORMAT($column, '%Y-W%v')",
            },
            'month' => match ($driver) {
                'sqlite' => "strftime('%Y-%m', $column)",
                'pgsql' => "TO_CHAR($column, 'YYYY-MM')",
                default => "DATE_FORMAT($column, '%Y-%m')",
            },
            default => match ($driver) { // day
                'sqlite' => "strftime('%Y-%m-%d', $column)",
                'pgsql' => "TO_CHAR($column, 'YYYY-MM-DD')",
                default => "DATE_FORMAT($column, '%Y-%m-%d')",
            },
        };
    }

    private function dateDiff(string $column = 'invoices.created_at'): string
    {
        return match (DB::getDriverName()) {
            'sqlite' => "CAST(julianday('now') - julianday($column) AS INTEGER)",
            'pgsql' => "EXTRACT(DAY FROM NOW() - $column)::INTEGER",
            default => "DATEDIFF(NOW(), $column)",
        };
    }
}
