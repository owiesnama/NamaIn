<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class Stock extends Pivot
{
    protected $guarded = [];

    protected static function booted(): void
    {
        // No parent::booted() because Pivot doesn't have it, but we want to unguard it
        static::unguard();
    }

    /**
     * Attribute to append to this stock.
     *
     * @var array<string>
     */
    protected $appends = ['totalCost'];

    /**
     * The average cost of this stock (reads from the materialized product column).
     */
    public function getAverageCostAttribute(): float|int
    {
        return $this->product->average_cost ?: ($this->product->cost ?? 0);
    }

    /**
     * The total cost of this stock.
     */
    public function getTotalCostAttribute(): float|int
    {
        return $this->relationLoaded('product') || $this->product_id
            ? $this->quantity * $this->average_cost
            : 0;
    }

    /**
     * The product of this stock.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The storage of this stock.
     */
    public function storage(): BelongsTo
    {
        return $this->belongsTo(Storage::class);
    }
}
