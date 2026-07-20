<?php

namespace App\Features;

use App\Models\Product;
use App\Models\Storage;
use App\Models\Tenant;
use App\Scopes\TenantScope;
use Closure;
use InvalidArgumentException;

/**
 * Maps each limit feature to a query that counts a tenant's current usage.
 *
 * Counts run with the global {@see TenantScope} removed and an explicit
 * `tenant_id` filter, so they are correct even in the super-admin panel where
 * no `currentTenant` is bound.
 */
class LimitUsage
{
    /**
     * @return array<string, Closure(Tenant): int>
     */
    protected function map(): array
    {
        return [
            Feature::MaxProducts->value => fn (Tenant $tenant): int => Product::withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenant->getKey())
                ->count(),

            Feature::MaxUsers->value => fn (Tenant $tenant): int => $tenant->users()->count(),

            Feature::MaxWarehouses->value => fn (Tenant $tenant): int => Storage::withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenant->getKey())
                ->count(),
        ];
    }

    public function has(Feature $feature): bool
    {
        return isset($this->map()[$feature->value]);
    }

    public function count(Feature $feature, Tenant $tenant): int
    {
        $counter = $this->map()[$feature->value]
            ?? throw new InvalidArgumentException("No usage mapping registered for limit feature [{$feature->value}].");

        return $counter($tenant);
    }
}
