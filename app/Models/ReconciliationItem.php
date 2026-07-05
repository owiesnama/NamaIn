<?php

namespace App\Models;

use App\Enums\ReconciliationType;
use App\Enums\ResolutionKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * The polymorphic reconciliation inbox row (Design 04 §1.1). A tenant-scoped
 * BaseModel that owns the lifecycle/audit for one divergence and morphs to its
 * concrete subject (oversell / credit breach / session variance / parked
 * mutation). Cloud-only — not pulled to devices in MVP, like its subjects.
 */
class ReconciliationItem extends BaseModel
{
    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    protected function casts(): array
    {
        return [
            'type' => ReconciliationType::class,
            'resolution' => ResolutionKind::class,
            'occurred_at' => 'datetime',
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * @param  Builder<ReconciliationItem>  $query
     * @return Builder<ReconciliationItem>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }
}
