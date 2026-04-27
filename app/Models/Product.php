<?php

namespace App\Models;

use App\Traits\WithTrashScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
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
    protected $appends = ['expired_at'];

    /**
     * Get the average cost of this product across all storages.
     */
    public function getAverageCostAttribute(): float|int
    {
        if (isset($this->attributes['computed_average_cost'])) {
            return (float) $this->attributes['computed_average_cost'] ?: ($this->cost ?? 0);
        }

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
     * Scope to add average cost as a subselect (avoids N+1 on lists).
     */
    public function scopeWithAverageCost(Builder $query): Builder
    {
        return $query->addSelect([
            'computed_average_cost' => Transaction::query()
                ->whereColumn('product_id', 'products.id')
                ->where('delivered', true)
                ->whereHas('invoice', fn ($q) => $q->where('invocable_type', Supplier::class))
                ->selectRaw('CASE WHEN COALESCE(SUM(base_quantity), 0) > 0 THEN SUM(base_quantity * unit_cost) / SUM(base_quantity) ELSE 0 END'),
        ]);
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
     * Scope to add stock aggregates as subselects (avoids N+1 on lists).
     */
    public function scopeWithStockAggregates(Builder $query): Builder
    {
        return $query
            ->withAverageCost()
            ->addSelect([
                'pending_sales_qty' => Transaction::query()
                    ->whereColumn('product_id', 'products.id')
                    ->where('delivered', false)
                    ->whereHas('invoice', fn ($q) => $q->where('invocable_type', Customer::class))
                    ->selectRaw('COALESCE(SUM(base_quantity), 0)'),
                'pending_purchases_qty' => Transaction::query()
                    ->whereColumn('product_id', 'products.id')
                    ->where('delivered', false)
                    ->whereHas('invoice', fn ($q) => $q->where('invocable_type', Supplier::class))
                    ->selectRaw('COALESCE(SUM(base_quantity), 0)'),
            ])
            ->withSum('stock as quantity_on_hand', 'stocks.quantity');
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
     * Get stock insights for this product.
     *
     * @return array<int, array{type: string, message: string}>
     */
    public function getInsights(): array
    {
        $insights = [];
        $qtyOnHand = $this->quantityOnHand();
        $pendingSales = $this->pendingSalesQuantity();
        $availableQty = $this->availableQuantity();
        $pendingPurchases = $this->pendingPurchaseQuantity();

        if ($pendingSales > $qtyOnHand) {
            $insights[] = [
                'type' => 'danger',
                'message' => __('Product overcommitted: :units units needed', ['units' => number_format($pendingSales - $qtyOnHand, 2)]),
            ];
        }

        if ($qtyOnHand == 0) {
            $insights[] = [
                'type' => 'danger',
                'message' => __('Out of Stock'),
            ];
        } elseif ($availableQty <= ($this->alert_quantity ?? config('namain.min_quantity_acceptable')) && $availableQty > 0) {
            $insights[] = [
                'type' => 'warning',
                'message' => __('Low stock alert: :units units remaining', ['units' => number_format($availableQty, 2)]),
            ];
        }

        if ($pendingPurchases > 0) {
            $insights[] = [
                'type' => 'info',
                'message' => __('Incoming stock: :units units expected', ['units' => number_format($pendingPurchases, 2)]),
            ];
        }

        return $insights;
    }

    /**
     * Sync units for this product.
     *
     * @param  array<int, array{name: string, conversion_factor: float|int}>  $units
     */
    public function syncUnits(array $units): void
    {
        $this->units()->delete();

        $formattedUnits = collect($units)->map(function ($unit) {
            return [
                'name' => $unit['name'],
                'conversion_factor' => $unit['conversion_factor'],
            ];
        })->toArray();

        $this->units()->createMany($formattedUnits);
    }

    /**
     * Get how many days went since the expiration date.
     */
    public function getExpiredAtAttribute(): int
    {
        return isset($this->attributes['expire_date']) && $this->attributes['expire_date'] ? now()->diffInDays($this->attributes['expire_date'], false) : 0;
    }
}
