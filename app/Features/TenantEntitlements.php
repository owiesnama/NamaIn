<?php

namespace App\Features;

use App\Models\Tenant;
use InvalidArgumentException;

/**
 * The resolved entitlements for one tenant.
 *
 * Built once by {@see EntitlementManager} from the tenant's active plan values
 * and live overrides, then answers typed questions about them. Boolean and
 * limit features are strictly separated: asking the wrong kind throws.
 */
class TenantEntitlements
{
    /**
     * @param  array<string, mixed>  $planValues  feature_key => raw plan value
     * @param  array<string, mixed>  $overrides  feature_key => raw live override value
     */
    public function __construct(
        private readonly Tenant $tenant,
        private readonly array $planValues,
        private readonly array $overrides,
        private readonly LimitUsage $usage,
    ) {}

    public function enabled(Feature $feature): bool
    {
        $this->assertType($feature, FeatureType::Boolean);

        // Strict: any falsy encoding (0, "0", false, null, "") resolves to off.
        return filter_var($this->raw($feature), FILTER_VALIDATE_BOOL);
    }

    public function limit(Feature $feature): ?int
    {
        $this->assertType($feature, FeatureType::Limit);

        $raw = $this->raw($feature);

        return $raw === null ? null : (int) $raw;
    }

    public function usage(Feature $feature): int
    {
        $this->assertType($feature, FeatureType::Limit);

        return $this->usage->count($feature, $this->tenant);
    }

    public function remaining(Feature $feature): ?int
    {
        $limit = $this->limit($feature);

        if ($limit === null) {
            return null; // unlimited
        }

        return max(0, $limit - $this->usage($feature));
    }

    public function allows(Feature $feature, int $wanted = 1): bool
    {
        $remaining = $this->remaining($feature);

        return $remaining === null || $remaining >= $wanted;
    }

    /**
     * Serializable snapshot for the frontend (booleans + limit caps; no usage).
     *
     * @return array{features: array<string, bool>, limits: array<string, int|null>}
     */
    public function toArray(): array
    {
        $features = [];
        foreach (Feature::booleans() as $feature) {
            $features[$feature->value] = $this->enabled($feature);
        }

        $limits = [];
        foreach (Feature::limits() as $feature) {
            $limits[$feature->value] = $this->limit($feature);
        }

        return ['features' => $features, 'limits' => $limits];
    }

    /**
     * The effective raw value: live override, else plan value, else default.
     */
    private function raw(Feature $feature): mixed
    {
        if (array_key_exists($feature->value, $this->overrides)) {
            return $this->overrides[$feature->value];
        }

        if (array_key_exists($feature->value, $this->planValues)) {
            return $this->planValues[$feature->value];
        }

        return $feature->default();
    }

    private function assertType(Feature $feature, FeatureType $expected): void
    {
        if ($feature->type() !== $expected) {
            throw new InvalidArgumentException(
                "Feature [{$feature->value}] is a {$feature->type()->value} feature; expected {$expected->value}."
            );
        }
    }
}
