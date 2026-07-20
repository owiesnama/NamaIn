<?php

namespace App\Features\Facades;

use App\Features\EntitlementManager;
use App\Features\Feature;
use App\Features\TenantEntitlements;
use App\Models\Tenant;
use Illuminate\Support\Facades\Facade;

/**
 * @method static TenantEntitlements for(Tenant $tenant)
 * @method static bool enabled(Feature $feature)
 * @method static int|null limit(Feature $feature)
 * @method static int usage(Feature $feature)
 * @method static int|null remaining(Feature $feature)
 * @method static bool allows(Feature $feature, int $wanted = 1)
 * @method static void flush(?Tenant $tenant = null)
 *
 * @see EntitlementManager
 */
class Entitlements extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return EntitlementManager::class;
    }
}
