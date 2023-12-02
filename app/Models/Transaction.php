<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    /**
     * Format the quantity in html tags.
     */
    public function normalizedQuantityHTML(): string
    {
        if (! $this->unit) {
            return "{$this->quantity} <strong>(Base unit)</strong>";
        }
        $unit = $this->unit->name;

        return "$this->quantity <storng>($unit)</storng>";
    }
}
