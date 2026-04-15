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

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (Product $product) {
            $product->currency = $product->currency ?? preference('currency', 'SDG');
        });
    }

    /**
     * List of the attributes to append to the product
     *
     * @var array<string>
     */
    protected $appends = ['expired_at', 'average_cost', 'pending_sales', 'pending_purchases', 'available_qty'];

    /**
     * Get the average cost of this product across all storages.
     */
    public function getAverageCostAttribute(): float|int
    {
        $totalPurchasedQty = $this->transactions()
            ->where('delivered', true)
            ->whereHas('invoice', fn ($query) => $query->where('invocable_type', Supplier::class))
            ->sum('base_quantity');

        if ($totalPurchasedQty <= 0) {
            return $this->cost ?? 0;
        }

        $totalPurchasedCost = $this->transactions()
            ->where('delivered', true)
            ->whereHas('invoice', fn ($query) => $query->where('invocable_type', Supplier::class))
            ->sum(\DB::raw('base_quantity * unit_cost'));

        return $totalPurchasedCost / $totalPurchasedQty;
    }

    /**
     * Sum of unexecuted purchase invoice items.
     */
    public function pendingPurchaseQuantity(): float|int
    {
        return $this->transactions()
            ->where('delivered', false)
            ->whereHas('invoice', fn ($query) => $query->where('invocable_type', Supplier::class))
            ->sum('base_quantity');
    }

    /**
     * Sum of unexecuted sales invoice items.
     */
    public function pendingSalesQuantity(): float|int
    {
        return $this->transactions()
            ->where('delivered', false)
            ->whereHas('invoice', fn ($query) => $query->where('invocable_type', Customer::class))
            ->sum('base_quantity');
    }

    /**
     * Quantity on hand minus pending sales.
     */
    public function availableQuantity(): float|int
    {
        return $this->quantityOnHand() - $this->pendingSalesQuantity();
    }

    /**
     * Quantity on hand plus pending purchases.
     */
    public function expectedQuantity(): float|int
    {
        return $this->quantityOnHand() + $this->pendingPurchaseQuantity();
    }

    public function getPendingSalesAttribute(): float|int
    {
        return $this->pendingSalesQuantity();
    }

    public function getPendingPurchasesAttribute(): float|int
    {
        return $this->pendingPurchaseQuantity();
    }

    public function getAvailableQtyAttribute(): float|int
    {
        return $this->availableQuantity();
    }

    /**
     * Transactions associated with this product.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

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
        return isset($this->attributes['expire_date']) && $this->attributes['expire_date'] ? now()->diffInDays($this->attributes['expire_date'], false) : 0;
    }
}
