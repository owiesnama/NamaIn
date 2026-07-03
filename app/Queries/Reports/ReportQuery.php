<?php

namespace App\Queries\Reports;

use Closure;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

abstract class ReportQuery
{
    /**
     * Cache a report result under a tenant-scoped key.
     */
    protected function remember(string $key, Closure $callback): mixed
    {
        return Cache::remember($this->cacheKey($key), $this->cacheTtl(), $callback);
    }

    /**
     * How long a cached result lives in production.
     */
    protected function cacheMinutes(): int
    {
        return 5;
    }

    /**
     * Reports that hand-roll joins on tenant-owned tables scope through this.
     */
    protected function tenantId(): int
    {
        return app()->has('currentTenant') ? app('currentTenant')->id : 0;
    }

    protected function dateFormat(string $groupBy, string $column = 'transactions.created_at'): string
    {
        return DB::dateFormat($groupBy, $column);
    }

    protected function dateDiff(string $column = 'invoices.created_at'): string
    {
        return DB::dateDiff($column);
    }

    private function cacheKey(string $key): string
    {
        return "tenant_{$this->tenantId()}_report_{$key}";
    }

    private function cacheTtl(): DateTimeInterface|int
    {
        // Only cache report results in production. Outside it (local, testing,
        // and the Cypress E2E env which runs as 'local') results must stay fresh
        // so a query right after seeding data isn't served a stale empty result.
        if (! app()->environment('production')) {
            return 0;
        }

        return now()->addMinutes($this->cacheMinutes());
    }
}
