<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class Stock extends Pivot
{
    use HasFactory;

    /**
     * Attribute to append to this stock.
     *
     * @var array<string>
     */
    protected $appends = ['totalCost'];

    /**
     * The total cost of this stock.
     *
     * @return float|int
     */
    public function getTotalCostAttribute(): float|int
    {
        return $this->product ? $this->quantity * $this->product->cost : 0;
    }

    /**
     * The product of this stock.
     *
     * @return BelongsTo
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The storage of this stock.
     *
     * @return BelongsTo
     */
    public function storage(): BelongsTo
    {
        return $this->belongsTo(Storage::class);
    }
}
