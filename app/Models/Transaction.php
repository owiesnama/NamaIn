<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Transaction extends BaseModel
{
    use HasFactory;

    public $with = ['product', 'unit', 'storage'];

    protected static $unguarded = false;

    protected $fillable = ['product_id', 'storage_id', 'quantity', 'base_quantity', 'unit_id', 'price', 'description'];

    protected $appends = ['type'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
    public function storage()
    {
        return $this->belongsTo(Storage::class);
    }
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function total()
    {
        return $this->quantity * $this->price;
    }

    public function type(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->invoice->invoicable_type == Customer::class ? 'Sales' : 'Purchases'
        );
    }

    public function for($storage)
    {
        $this->storage_id = $storage->id;
        return $this;
    }
    public function deduct()
    {
        $this->storage->deductStock([
            'product' => $this->product_id,
            'quantity' => $this->base_quantity,
        ]);

        return $this;
    }
    public function add()
    {
        $this->storage->addStock([
            'product' => $this->product_id,
            'quantity' => $this->base_quantity,
        ]);

        return $this;
    }
    public function deliver()
    {
        $this->delivered = true;
        $this->save();
    }
    public function normalizedQuantity()
    {
        if (!$this->unit) {
            return "{$this->quantity} <strong>(Base unit)</strong>";
        }

        return "$this->quantity <storng>($this->unit?->name)</storng>";
    }
}
