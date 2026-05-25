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
use Illuminate\Support\Facades\DB;

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
            $product->price = $product->price ?? $product->cost ?? 0;
            $product->average_cost = $product->average_cost ?? $product->cost ?? 0;
        });
    }

    /**
     * List of the attributes to append to the product
     *
     * @var array<string>
     */
    protected $appends = ['expired_at'];

    public function recalculateAverageCost(): void
    {
        $result = DB::table('transactions')
            ->join('invoices', 'transactions.invoice_id', '=', 'invoices.id')
            ->where('transactions.product_id', $this->id)
            ->where('transactions.delivered', true)
            ->whereNull('transactions.deleted_at')
            ->whereNull('invoices.deleted_at')
            ->where('invoices.invocable_type', Supplier::class)
            ->selectRaw('SUM(transactions.base_quantity) as total_qty, SUM(transactions.base_quantity * transactions.unit_cost) as total_cost')
            ->first();

        $newCost = ($result && $result->total_qty > 0)
            ? (int) round($result->total_cost / $result->total_qty)
            : ($this->cost ?? 0);

        DB::table('products')->where('id', $this->id)->update(['average_cost' => $newCost]);

        $this->average_cost = $newCost;
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
        if (($this->attributes['pending_sales_qty'] ?? null) !== null) {
            return (int) $this->attributes['pending_sales_qty'];
        }

        return $this->pendingSalesQuantity();
    }

    public function getPendingPurchasesAttribute(): float|int
    {
        if (($this->attributes['pending_purchases_qty'] ?? null) !== null) {
            return (int) $this->attributes['pending_purchases_qty'];
        }

        return $this->pendingPurchaseQuantity();
    }

    public function getAvailableQtyAttribute(): float|int
    {
        if (($this->attributes['pending_sales_qty'] ?? null) !== null) {
            $qtyOnHand = (int) ($this->attributes['quantity_on_hand'] ?? 0);

            return $qtyOnHand - (int) $this->attributes['pending_sales_qty'];
        }

        return $this->availableQuantity();
    }

    /**
     * Scope to add stock aggregates as subselects (avoids N+1 on lists).
     */
    public function scopeWithStockAggregates(Builder $query): Builder
    {
        return $query
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
            'price' => 'integer',
            'average_cost' => 'integer',
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
