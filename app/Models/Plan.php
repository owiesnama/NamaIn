<?php

namespace App\Models;

use App\Features\Facades\Entitlements;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A subscription plan — global catalog data (NOT tenant-scoped).
 *
 * @property array<string, string> $name
 */
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::unguard();

        // Any write to an entitlement source invalidates the per-request
        // entitlement cache; self-flushing here means no call site can forget.
        static::saved(fn () => Entitlements::flush());
        static::deleted(fn () => Entitlements::flush());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'price' => 'decimal:2',
        ];
    }

    public function planFeatures(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Localized display name, falling back to English then the first value.
     */
    public function displayName(?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();
        $name = $this->name ?? [];

        return $name[$locale] ?? $name['en'] ?? (reset($name) ?: '');
    }
}
