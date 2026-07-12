<?php

namespace App\Models;

use App\Enums\MovementType;
use App\Exceptions\StockMovementIsImmutableException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends BaseModel
{
    use HasFactory;

    /**
     * Enforce the append-only ledger invariant: movements are never updated or
     * deleted to change stock; corrections are new compensating rows.
     */
    protected static function booted(): void
    {
        parent::booted();

        static::updating(function () {
            throw new StockMovementIsImmutableException('updated');
        });

        static::deleting(function () {
            throw new StockMovementIsImmutableException('deleted');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'movement_type' => MovementType::class,
        ];
    }

    public function storage(): BelongsTo
    {
        return $this->belongsTo(Storage::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function movable(): MorphTo
    {
        return $this->morphTo();
    }
}
