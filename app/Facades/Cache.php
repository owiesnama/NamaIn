<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Tenant-aware cache facade.
 *
 * All keys are automatically scoped to the current tenant.
 *
 * @method static string key(string $key)
 * @method static mixed  rememberForever(string $key, \Closure $callback)
 * @method static bool   forget(string $key)
 *
 * @see \App\Services\Core\Cache
 */
class Cache extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'tenant-cache';
    }
}
