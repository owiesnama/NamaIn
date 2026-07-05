<?php

namespace App\Models;

use App\Enums\RejectionReason;
use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A terminally-rejected push mutation (Design 04 §1.2). Not a syncable
 * BaseModel — cloud-only, storing the raw envelope for inspection/replay. One
 * row per mutation (unique tenant_id + idempotency_key), so a re-push of a
 * still-broken mutation never double-parks nor double-raises an inbox item.
 */
class ParkedMutation extends Model
{
    use HasFactory, HasPublicId;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rejection_reason' => RejectionReason::class,
            'envelope' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
