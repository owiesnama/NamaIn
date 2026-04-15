<?php

namespace App\Models;

use App\Traits\WithTrashScope;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @method static delivered(Carbon $datetime).
 */
class Transaction extends BaseModel
{
    use HasFactory, SoftDeletes, WithTrashScope;

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
        ];
    }

    /**
     * @var array<string>
     */
    protected $appends = ['type', 'created_at_human'];

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

        $storage->deductStock([
            'product' => $this->product_id,
            'quantity' => $this->base_quantity,
        ]);

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

        $storage->addStock([
            'product' => $this->product_id,
            'quantity' => $this->base_quantity,
        ]);

        return $this;
    }

    /**
     * Mark this transaction as delivered.
     */
    public function deliver(): void
    {
        $this->delivered = true;
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
    public function reverse(): void
    {
        $storage = $this->storage;

        if ($this->invoice?->invocable_type === Customer::class) {
            // Sales return: Add stock back
            $storage->addStock([
                'product' => $this->product_id,
                'quantity' => $this->base_quantity,
            ]);
        } else {
            // Purchase return: Deduct stock
            $storage->deductStock([
                'product' => $this->product_id,
                'quantity' => $this->base_quantity,
            ]);
        }
    }
}
