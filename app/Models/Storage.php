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
     * @var array
     */
    protected $fillable = [
        'name',
        'address',
    ];

    protected $appends = ['stockCount'];

    public function stock(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withPivot([
            'quantity',
        ])->withTimestamps();
    }

    public function qunatityOf($product)
    {
        $productId = is_int($product) ?: $product->id;

        return (int) $this->stock()->find($productId)?->pivot?->quantity ?: 0;
    }

    public function hasStockFor($productId): bool
    {
        return $this->stock()->where('product_id', $productId)->where('quantity', '>', 0)->exists();
    }

    public function hasNoStockFor($productId): bool
    {
        return ! $this->hasStockFor($productId);
    }

    public function hasEnoughStockFor($productId, $quantity): bool
    {
        if ($this->hasNoStockFor($productId)) {
            return false;
        }

        return $this->stock()->find($productId)->pivot->quantity >= $quantity;
    }

    public function hasNoEnoughStockFor($productId, $quantity)
    {
        return ! $this->hasEnoughStockFor($productId, $quantity);
    }

    public function addStock($attributes)
    {
        if ($this->hasStockFor($attributes['product'])) {
            return $this->stock()->find($attributes['product'])->pivot->increment('quantity', $attributes['quantity']);
        }

        return $this->stock()->attach([$attributes['product'] => ['quantity' => $attributes['quantity']]]);
    }

    public function deductStock($attributes)
    {
        if ($this->hasNoStockFor($attributes['product'])) {
            return false;
        }
        $stock = $this->stock()->find($attributes['product']);

        return (bool) $stock->pivot->decrement('quantity', $attributes['quantity']);
    }

    public function getStockCountAttribute()
    {
        return $this->stock->count();
    }
}
