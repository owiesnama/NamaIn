<?php

namespace App\Models;

use App\Enums\StorageType;
use App\Exceptions\InsufficientStockException;
use App\Traits\WithTrashScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Storage extends BaseModel
{
    use HasFactory, SoftDeletes,WithTrashScope;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected array $searchable = ['name', 'address'];

    /**
     * The attributes to be appended to this
     *
     * @var array<string>
     */
    protected $appends = ['created_at_human'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => StorageType::class,
        ];
    }

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
        $productId = is_int($product) ? $product : $product->id;

        return (int) DB::table('stocks')
            ->where('storage_id', $this->id)
            ->where('product_id', $productId)
            ->value('quantity') ?: 0;
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
    public function addStock(Product|int $product, int $quantity, string $reason, ?Model $movable = null, ?User $actor = null): void
    {
        $productId = $product instanceof Product ? $product->id : $product;
        $productModel = $product instanceof Product ? $product : Product::findOrFail($productId);

        DB::transaction(function () use ($productId, $productModel, $quantity, $reason, $movable, $actor) {
            $stockData = DB::table('stocks')
                ->where('storage_id', $this->id)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if (! $stockData) {
                DB::table('stocks')->insert([
                    'tenant_id' => $this->tenant_id,
                    'storage_id' => $this->id,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $quantityBefore = 0;
            } else {
                DB::table('stocks')
                    ->where('id', $stockData->id)
                    ->increment('quantity', $quantity, ['updated_at' => now()]);
                $quantityBefore = $stockData->quantity;
            }

            $this->recordMovement($productModel, $quantity, $quantityBefore, $quantityBefore + $quantity, $reason, $movable, $actor);
        });
    }

    /**
     * Deduct a stock from this store.
     */
    public function deductStock(Product|int $product, int $quantity, string $reason, ?Model $movable = null, ?User $actor = null): void
    {
        $productId = $product instanceof Product ? $product->id : $product;
        $productModel = $product instanceof Product ? $product : Product::findOrFail($productId);

        DB::transaction(function () use ($productId, $productModel, $quantity, $reason, $movable, $actor) {
            $stockData = DB::table('stocks')
                ->where('storage_id', $this->id)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if (! $stockData || $stockData->quantity < $quantity) {
                throw new InsufficientStockException($productModel, $this);
            }

            DB::table('stocks')
                ->where('id', $stockData->id)
                ->decrement('quantity', $quantity, ['updated_at' => now()]);

            $quantityBefore = $stockData->quantity;

            $this->recordMovement($productModel, -$quantity, $quantityBefore, $quantityBefore - $quantity, $reason, $movable, $actor);
        });
    }

    /**
     * Set stock to a specific quantity
     */
    public function setStockTo(Product|int $product, int $quantity, string $reason, ?Model $movable = null, ?User $actor = null): void
    {
        $productId = $product instanceof Product ? $product->id : $product;
        $productModel = $product instanceof Product ? $product : Product::findOrFail($productId);

        DB::transaction(function () use ($productId, $productModel, $quantity, $reason, $movable, $actor) {
            DB::table('stocks')->insertOrIgnore([
                'tenant_id' => $this->tenant_id,
                'storage_id' => $this->id,
                'product_id' => $productId,
                'quantity' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $stock = $this->stock()->where('product_id', $productId)->lockForUpdate()->first();
            $quantityBefore = $stock->pivot->quantity;
            $delta = $quantity - $quantityBefore;

            $stock->pivot->update(['quantity' => $quantity]);

            $this->recordMovement($productModel, $delta, $quantityBefore, $quantity, $reason, $movable, $actor);
        });
    }

    protected function recordMovement(Product $product, int $quantity, int $before, int $after, string $reason, ?Model $movable = null, ?User $actor = null): void
    {
        $this->movements()->create([
            'tenant_id' => $this->tenant_id,
            'product_id' => $product->id,
            'user_id' => $actor?->id,
            'movable_type' => $movable?->getMorphClass(),
            'movable_id' => $movable?->getKey(),
            'reason' => $reason,
            'quantity' => $quantity,
            'quantity_before' => $before,
            'quantity_after' => $after,
        ]);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Get the transactions for this storage
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get the stock total count for this storage
     */
    public function getStockCountAttribute(): int
    {
        return $this->stock()->count();
    }

    public function scopeWarehouses(Builder $query): void
    {
        $query->where('type', StorageType::WAREHOUSE);
    }

    public function scopeSalePoints(Builder $query): void
    {
        $query->where('type', StorageType::SALE_POINT);
    }

    public function isWarehouse(): bool
    {
        return $this->type === StorageType::WAREHOUSE;
    }

    public function isSalePoint(): bool
    {
        return $this->type === StorageType::SALE_POINT;
    }
}
