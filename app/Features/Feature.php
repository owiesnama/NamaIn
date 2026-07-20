<?php

namespace App\Features;

/**
 * The code-defined catalog of gateable features.
 *
 * This enum is the single source of truth for *what features exist*. Plans
 * (admin-managed data) reference these keys and assign values; the
 * {@see EntitlementManager} resolves the effective value for a
 * tenant. Adding a feature here is the first step; a boolean feature is then
 * gated with the `feature:` middleware, and a limit feature must also gain a
 * mapping in {@see LimitUsage}.
 */
enum Feature: string
{
    // Boolean capabilities
    case Bookings = 'bookings';
    case Pos = 'pos';
    case MultiWarehouse = 'multi_warehouse';
    case Quotes = 'quotes';
    case AdvancedReports = 'advanced_reports';
    case Exports = 'exports';
    case Cheques = 'cheques';

    // Numeric limits (quotas)
    case MaxProducts = 'max_products';
    case MaxUsers = 'max_users';
    case MaxWarehouses = 'max_warehouses';

    /**
     * Whether this feature is a boolean capability or a numeric limit.
     */
    public function type(): FeatureType
    {
        return match ($this) {
            self::MaxProducts,
            self::MaxUsers,
            self::MaxWarehouses => FeatureType::Limit,
            default => FeatureType::Boolean,
        };
    }

    /**
     * Grouping key used to organize features in the admin plan editor.
     */
    public function group(): string
    {
        return match ($this) {
            self::Bookings, self::Pos => 'operations',
            self::MultiWarehouse, self::MaxWarehouses => 'inventory',
            self::Quotes => 'sales',
            self::AdvancedReports => 'reports',
            self::Exports => 'data',
            self::Cheques => 'payments',
            self::MaxProducts => 'catalog',
            self::MaxUsers => 'team',
        };
    }

    /**
     * Translation key for the human-readable label (resolve via `__()`).
     */
    public function labelKey(): string
    {
        return 'features.'.$this->value;
    }

    /**
     * Fallback value when neither a per-tenant override nor the active plan
     * specifies this feature. Booleans default off; limits default to 0 (deny).
     */
    public function default(): bool|int|null
    {
        return match ($this->type()) {
            FeatureType::Boolean => false,
            FeatureType::Limit => 0,
        };
    }

    public function isBoolean(): bool
    {
        return $this->type() === FeatureType::Boolean;
    }

    public function isLimit(): bool
    {
        return $this->type() === FeatureType::Limit;
    }

    /**
     * @return array<int, self>
     */
    public static function booleans(): array
    {
        return array_values(array_filter(self::cases(), fn (self $f) => $f->isBoolean()));
    }

    /**
     * @return array<int, self>
     */
    public static function limits(): array
    {
        return array_values(array_filter(self::cases(), fn (self $f) => $f->isLimit()));
    }
}
