<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Storage extends BaseModel
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'address',
    ];

    /**
     * The attributes to be appended to this
     *
     * @var array<string>
     */
    protected $appends = ['stockCount'];

    /**
     * Stock for this storage
     */
    public function stock(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'stocks')->withPivot([
            'quantity',
        ])->withTimestamps();
    }

    /**
     * Quantity of given product on this storage
     */
    public function quantityOf(Product|int $product): int
    {
        $productId = is_int($product) ?: $product->id;

        return (int) $this->stock()->find($productId)?->pivot?->quantity ?: 0;
    }

    /**
     * Check this store if it has a stock for a given product id.
     */
    public function hasStockFor($productId): bool
    {
        return $this->stock()->where('product_id', $productId)->where('quantity', '>', 0)->exists();
    }

    /**
     * Check this store if it has not stock for a given product id.
     */
    public function hasNoStockFor($productId): bool
    {
        return ! $this->hasStockFor($productId);
    }

    /**
     * Check this store if it has a given quantity from a given product id.
     */
    public function hasEnoughStockFor(int $productId, int $quantity): bool
    {
        if ($this->hasNoStockFor($productId)) {
            return false;
        }

        return $this->stock()->find($productId)->pivot->quantity >= $quantity;
    }

    /**
     * Check this store if it has a given quantity from a given product id.
     */
    public function hasNoEnoughStockFor(int $productId, int $quantity): bool
    {
        return ! $this->hasEnoughStockFor($productId, $quantity);
    }

    /**
     * Add a stock to this storage
     */
    public function addStock(array $attributes): bool
    {
        if ($this->hasNoStockFor($attributes['product'])) {
            $this->stock()->attach([$attributes['product'] => ['quantity' => $attributes['quantity']]]);
        } else {
            $this->stock()->find($attributes['product'])->pivot->increment('quantity', $attributes['quantity']);
        }

        return true;
    }

    /**
     * Deduct a stock from this store.
     */
    public function deductStock(array $attributes): bool
    {
        if ($this->hasNoStockFor($attributes['product'])) {
            return false;
        }
        $stock = $this->stock()->find($attributes['product']);

        return (bool) $stock->pivot->decrement('quantity', $attributes['quantity']);
    }

    /**
     * Get the stock total count for this storage
     */
    public function getStockCountAttribute(): int
    {
        return $this->stock->count();
    }
}
