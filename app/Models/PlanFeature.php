<?php

namespace App\Models;

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
