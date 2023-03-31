<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class InvoiceDetails extends BaseModel
{
    use HasFactory;

    protected static $unguarded = false;

    protected $fillable = ['product_id', 'quantity', 'base_quantity', 'unit_id', 'price', 'description'];

    public $with = ['product', 'unit'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function normalizedQuantity()
    {
        if (! $this->unit) {
            return "{$this->quantity} <strong>(Base unit)</strong>";
        }

        return "$this->quantity <storng>($this->unit?->name)</storng>";
    }

    public function total()
    {
        return $this->quantity * $this->price;
    }
}
