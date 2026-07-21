<?php

namespace App\Models;

use App\Features\Facades\Entitlements;
use Database\Factories\TenantFeatureOverrideFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A per-tenant override of a feature value, on top of the tenant's plan.
 *
 * Deliberately NOT tenant-scoped (see {@see Subscription}). A live override
 * (no `expires_at`, or one in the future) wins over the plan's value.
 */
class TenantFeatureOverride extends Model
{
    /** @use HasFactory<TenantFeatureOverrideFactory> */
    use HasFactory;

    protected $table = 'feature_tenant';

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
            'value' => 'json',
            'expires_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isLive(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
