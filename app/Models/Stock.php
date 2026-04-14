<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\DB;

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
     * The average cost of this stock.
     */
    public function getAverageCostAttribute(): float|int
    {
        $totalPurchasedQty = $this->product->transactions()
            ->where('delivered', true)
            ->whereHas('invoice', fn ($query) => $query->where('invocable_type', Supplier::class))
            ->sum('base_quantity');

        if ($totalPurchasedQty <= 0) {
            return $this->product->cost ?? 0;
        }

        $totalPurchasedCost = $this->product->transactions()
            ->where('delivered', true)
            ->whereHas('invoice', fn ($query) => $query->where('invocable_type', Supplier::class))
            ->sum(DB::raw('base_quantity * unit_cost'));

        return $totalPurchasedCost / $totalPurchasedQty;
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
