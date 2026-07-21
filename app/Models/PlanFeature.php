<?php

namespace App\Models;

use App\Features\Facades\Entitlements;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single feature value assigned to a plan.
 *
 * `value` is a boolean for boolean features and an integer (or null =
 * unlimited) for limit features — stored as JSON so scalars round-trip.
 */
class PlanFeature extends Model
{
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
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
