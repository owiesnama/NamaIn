<?php

namespace App\Models;

use App\Traits\WithTrashScope;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @method static delivered(Carbon $datetime).
 */
class Transaction extends BaseModel
{
    use HasFactory, SoftDeletes, WithTrashScope;

    protected array $searchable = ['description'];

    protected array $searchableRelationsAttributes = ['product.name', 'product.categories.name'];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (Transaction $transaction) {
            $transaction->currency = $transaction->currency ?? $transaction->invoice?->currency ?? preference('currency', 'SDG');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delivered' => 'boolean',
            'created_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * @var array<string>
     */
    protected $appends = ['type', 'created_at_human', 'received_quantity', 'remaining_quantity'];

    public function receipts(): HasMany
    {
        return $this->hasMany(TransactionReceipt::class);
    }

    public function getReceivedQuantityAttribute(): int
    {
        return (int) $this->receipts()->sum('quantity');
    }

    public function getRemainingQuantityAttribute(): int
    {
        return max(0, (int) $this->base_quantity - $this->received_quantity);
    }

    public function isFullyReceived(): bool
    {
        return $this->remaining_quantity <= 0;
    }

    /**
     * The invoice for this transaction.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Storage of this transaction.
     */
    public function storage(): BelongsTo
    {
        return $this->belongsTo(Storage::class);
    }

    /**
     * Product of this transaction.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Unit of this transaction product.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * the total price for this transaction.
     */
    public function total(): float
    {
        return $this->quantity * $this->price;
    }

    /**
     * The total cost of this transaction.
     */
    public function getTotalCostAttribute(): float|int
    {
        return $this->base_quantity * ($this->unit_cost ?? 0);
    }

    /**
     *  Type of this transaction.
     */
    public function getTypeAttribute(): string
    {
        return $this->invoice?->invocable_type == Customer::class ? 'Sales' : 'Purchases';
    }

    /**
     * Convenient method to assign the storage to this instance
     */
    public function for(Storage $storage): Transaction
    {
        $this->storage_id = $storage->id;

        return $this;
    }

    /**
     * Deduct this transaction from the storage
     */
    public function deduct(?Storage $storage = null): Transaction
    {
        $storage = $storage ?? $this->storage()->first();

        $storage->deductStock(
            product: $this->product_id,
            quantity: (int) $this->base_quantity,
            reason: 'invoice_deduction',
            movable: $this
        );

        return $this;
    }

    /**
     * add this transaction to the storage.
     */
    public function add(?Storage $storage = null): Transaction
    {
        if ($this->getTypeAttribute() === 'Purchases') {
            $this->unit_cost = $this->product?->cost;
            $this->save();
        }

        $storage = $storage ?? $this->storage()->first();

        $storage->addStock(
            product: $this->product_id,
            quantity: (int) $this->base_quantity,
            reason: 'invoice_addition',
            movable: $this
        );

        return $this;
    }

    /**
     * Mark this transaction as delivered.
     */
    public function deliver(User $actor, ?Storage $fromStorage = null): void
    {
        $this->delivered = true;
        $this->delivered_at = now();
        $this->delivered_by = $actor->id;
        $this->fulfilled_from_storage_id = ($fromStorage ?? $this->storage)->id;
        $this->save();
    }

    public function scopeForCustomer(Builder $query): Builder
    {
        return $query->whereHas('invoice', fn ($q) => $q->where('invocable_type', Customer::class));
    }

    public function scopeForSupplier(Builder $query): Builder
    {
        return $query->whereHas('invoice', fn ($q) => $q->where('invocable_type', Supplier::class));
    }

    public function scopeTotalValue(Builder $query): float|int|string
    {
        return $query->sum(DB::raw('price * base_quantity'));
    }

    public function scopeOfType(Builder $builder, string $type): Builder
    {
        return $builder->where('invocable_type', $type);
    }

    /**
     * Filter invoices to delivered.
     */
    public function scopeDelivered(Builder $builder, ?Carbon $datetime = null): Builder
    {
        return $builder->where($this->getTable().'.delivered', true)
            ->when($datetime, fn ($query) => $query->where($this->getTable().'.created_at', '>', $datetime));
    }

    /**
     * Split this transaction into two: one for given quantity, another for remaining.
     */
    public function split(float|int $remaining): void
    {
        $this->base_quantity -= $remaining;
        $this->save();

        $this->replicateForRemaining($remaining);
    }

    /**
     * Replicate a transaction on the invoice for the remaining quantity.
     */
    public function replicateForRemaining($remaining): void
    {
        $newTransaction = $this->replicate();
        $newTransaction->delivered = false;
        $newTransaction->quantity = $this->unit ? ($remaining / $this->unit->conversion_factor) : $remaining;
        $newTransaction->base_quantity = $this->unit ? ($remaining * $this->unit->conversion_factor) : $remaining;
        $newTransaction->save();
    }

    /**
     * Reverse this transaction (inventory adjustment).
     */
    /**
     * Scope to filter transactions by type (Sales/Purchases).
     */
    public function scopeFilterByType(Builder $query, ?string $type): Builder
    {
        if (! $type || $type === 'All') {
            return $query;
        }

        return $query->whereHas('invoice', function ($q) use ($type) {
            if ($type === 'Sales') {
                $q->where('invocable_type', Customer::class);
            } elseif ($type === 'Purchases') {
                $q->where('invocable_type', '!=', Customer::class);
            }
        });
    }

    /**
     * Scope to filter transactions by storage.
     */
    public function scopeForStorage(Builder $query, $storageId): Builder
    {
        return $query->when($storageId, fn ($q) => $q->where('storage_id', $storageId));
    }

    /**
     * Scope to filter transactions by date range.
     */
    public function scopeInDateRange(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to));
    }

    /**
     * Get chart data for sales and purchases.
     */
    public static function getMonthlyStats(int $productId, int $months = 6): array
    {
        $startDate = now()->subMonths($months - 1)->startOfMonth();
        $dateLabels = collect();
        for ($i = $months - 1; $i >= 0; $i--) {
            $dateLabels->push(now()->subMonths($i)->format('Y-m'));
        }

        $baseQuery = static::where('product_id', $productId)
            ->where('created_at', '>=', $startDate);

        $salesQuery = (clone $baseQuery)->whereHas('invoice', fn ($q) => $q->where('invocable_type', Customer::class));
        $purchasesQuery = (clone $baseQuery)->whereHas('invoice', fn ($q) => $q->where('invocable_type', '!=', Customer::class));

        $format = match (config('database.default')) {
            'sqlite' => "strftime('%Y-%m', created_at)",
            'pgsql' => "to_char(created_at, 'YYYY-MM')",
            default => "DATE_FORMAT(created_at, '%Y-%m')",
        };

        $sales = $salesQuery->selectRaw("$format as month, SUM(quantity) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        $purchases = $purchasesQuery->selectRaw("$format as month, SUM(quantity) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        return [
            'labels' => $dateLabels->map(fn ($m) => \Carbon\Carbon::parse($m)->format('M Y'))->toArray(),
            'sales' => $dateLabels->map(fn ($m) => $sales->get($m, 0))->toArray(),
            'purchases' => $dateLabels->map(fn ($m) => $purchases->get($m, 0))->toArray(),
        ];
    }
}
