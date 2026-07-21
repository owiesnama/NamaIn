<?php

namespace App\Services\Sync;

use App\Features\Facades\Entitlements;
use App\Features\Feature;
use App\Models\Tenant;

/**
 * The per-tenant rollout gate for the offline initiative's behavioural changes:
 * register-scoped invoice serials and change-log capture activate only for
 * tenants whose plan (or override) grants {@see Feature::OfflineSync}.
 *
 * Schema-level groundwork stays unconditional — `public_id` minting, the R0
 * cloud register, and the idempotency store are inert without the sync API and
 * must already be in place the moment a tenant is switched on.
 */
class OfflineSync
{
    public static function enabledFor(?int $tenantId): bool
    {
        if ($tenantId === null) {
            return false;
        }

        $tenant = static::resolveTenant($tenantId);

        return $tenant !== null && Entitlements::for($tenant)->enabled(Feature::OfflineSync);
    }

    private static function resolveTenant(int $tenantId): ?Tenant
    {
        if (app()->bound('currentTenant') && app('currentTenant')->id === $tenantId) {
            return app('currentTenant');
        }

        return Tenant::find($tenantId);
    }
}
