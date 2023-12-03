<?php

namespace App\Models;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @method static delivered(Carbon $datetime): Builder
 */
class Transaction extends BaseModel
{
    /**
     * @var array<string>
     */
    public $with = ['product', 'unit', 'storage'];

    /**
     * @var bool
     */
    protected static $unguarded = false;

    /**
     * @var array<string>
     */
    protected $fillable = ['product_id', 'storage_id', 'quantity', 'base_quantity', 'unit_id', 'price', 'description'];

    /**
     * @var array<string>
     */
    protected $appends = ['type'];

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
     *  Type of this transaction.
     */
    public function type(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->invoice->invocable_type == Customer::class ? 'Sales' : 'Purchases'
        );
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
    public function deduct(): Transaction
    {
        $this->storage()->first()->deductStock([
            'product' => $this->product_id,
            'quantity' => $this->base_quantity,
        ]);

        return $this;
    }

    /**
     * add this transaction to the storage.
     */
    public function add(): Transaction
    {
        $this->storage()->first()->addStock([
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

    public function scopeOfType(Builder $builder, string $type): Builder
    {
        return $builder->where('invocable_type', $type);
    }

    /**
     * Filter invoices to delivered.
     */
    public function scopeDelivered(Builder $builder, Carbon $datetime = null): Builder
    {
        return $builder->where('delivered', true)
            ->when($datetime, fn ($query) => $query->where('created_at', '>', $datetime));
    }
}
