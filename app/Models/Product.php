<?php

namespace App\Models;

use App\Traits\WithTrashScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property Pivot $pivot
 */
class Product extends BaseModel
{
    use HasFactory, SoftDeletes, WithTrashScope;

    /**
     * List of searchable model's relation attributes
     *
     * @var array<string>
     */
    protected array $searchable = ['name', 'currency'];

    protected array $searchableRelationsAttributes = ['categories.name'];

    /**
     * List of fillable fields of the product
     *
     * @var array<string>
     */
    protected $fillable = ['name', 'cost', 'expire_date', 'currency', 'alert_quantity'];

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            $product->currency = $product->currency ?? preference('currency', 'USD');
        });
    }

    /**
     * List of the attributes to append to the product
     *
     * @var array<string>
     */
    protected $appends = ['expired_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expire_date' => 'date',
        ];
    }

    /**
     * The stock details for this product.
     */
    public function stock(): BelongsToMany
    {
        return $this->belongsToMany(Storage::class, 'stocks')->withPivot([
            'quantity',
        ])->withTimestamps();
    }

    /**
     * Unites associated with this product.
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /**
     * Categories associated with this product.
     */
    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable');
    }

    /**
     * Quantity on hand for this product
     */
    public function quantityOnHand(): int
    {
        return $this->stock->sum('pivot.quantity');
    }

    /**
     * Check whether this product went low on quantity.
     *
     * @return bool
     */
    public function isRunningLow()
    {
        return $this->quantityOnHand() <= ($this->alert_quantity ?? config('namain.min_quantity_acceptable'));
    }

    /**
     * Get the expiration date formatted.
     */
    public function getExpireDateAttribute($value): string
    {
        return $value ? Carbon::parse($value)->format('Y-m-d') : '';
    }

    /**
     * Get how many days went since the expiration date.
     */
    public function getExpiredAtAttribute(): int
    {
        return $this->attributes['expire_date'] ? now()->diffInDays($this->attributes['expire_date'], false) : 0;
    }
}
