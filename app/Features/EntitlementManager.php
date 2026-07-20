<?php

namespace App\Features;

use App\Features\Exceptions\NoTenantContextException;
use App\Models\Tenant;
use App\Providers\FeatureServiceProvider;
use App\Scopes\TenantScope;

/**
 * Resolves and caches per-tenant entitlements for the current request.
 *
 * Registered as a singleton (see {@see FeatureServiceProvider}),
 * so the cache is naturally per-request. The cache is keyed by tenant id, which
 * keeps per-tenant loop commands (that rebind `currentTenant`) correct.
 *
 * Ambient calls (`enabled()`, `limit()`, ...) resolve the tenant the same way
 * {@see TenantScope} does; if none is resolvable they throw rather
 * than silently returning "off".
 */
class EntitlementManager
{
    /** @var array<int|string, TenantEntitlements> */
    private array $cache = [];

    public function __construct(private readonly LimitUsage $usage) {}

    public function for(Tenant $tenant): TenantEntitlements
    {
        return $this->cache[$tenant->getKey()] ??= $this->build($tenant);
    }

    public function enabled(Feature $feature): bool
    {
        return $this->for($this->resolveTenant())->enabled($feature);
    }

    public function limit(Feature $feature): ?int
    {
        return $this->for($this->resolveTenant())->limit($feature);
    }

    public function usage(Feature $feature): int
    {
        return $this->for($this->resolveTenant())->usage($feature);
    }

    public function remaining(Feature $feature): ?int
    {
        return $this->for($this->resolveTenant())->remaining($feature);
    }

    public function allows(Feature $feature, int $wanted = 1): bool
    {
        return $this->for($this->resolveTenant())->allows($feature, $wanted);
    }

    /**
     * Drop the cached resolution for one tenant (or all), so the next check
     * re-reads plan/override data. Call after any entitlement-affecting write.
     */
    public function flush(?Tenant $tenant = null): void
    {
        if ($tenant) {
            unset($this->cache[$tenant->getKey()]);

            return;
        }

        $this->cache = [];
    }

    private function build(Tenant $tenant): TenantEntitlements
    {
        $planValues = [];
        if ($plan = $tenant->activePlan()) {
            foreach ($plan->planFeatures as $planFeature) {
                $planValues[$planFeature->feature_key] = $planFeature->value;
            }
        }

        $overrides = [];
        foreach ($tenant->liveFeatureOverrides() as $override) {
            $overrides[$override->feature_key] = $override->value;
        }

        return new TenantEntitlements($tenant, $planValues, $overrides, $this->usage);
    }

    private function resolveTenant(): Tenant
    {
        if (app()->bound('currentTenant')) {
            return app('currentTenant');
        }

        if (auth()->check() && auth()->user()->current_tenant_id) {
            if ($tenant = Tenant::find(auth()->user()->current_tenant_id)) {
                return $tenant;
            }
        }

        throw NoTenantContextException::make();
    }
}
