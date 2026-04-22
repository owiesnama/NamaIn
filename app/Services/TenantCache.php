<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class TenantCache
{
    public static function key(string $key): string
    {
        $tenantId = app()->bound('currentTenant') ? app('currentTenant')->id : null;

        if (! $tenantId) {
            throw new \RuntimeException('Cannot generate a tenant cache key outside of a tenant context.');
        }

        return "tenant_{$tenantId}_{$key}";
    }

    public static function rememberForever(string $key, \Closure $callback): mixed
    {
        return Cache::rememberForever(self::key($key), $callback);
    }

    public static function forget(string $key): bool
    {
        return Cache::forget(self::key($key));
    }
}
