<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InvoiceDetails extends BaseModel
{
    use HasFactory;

    public $guarded = [];

    public $with = ['product', 'unit'];

    public function baseQuantity(): Attribute
    {
        return Attribute::make(
            get: fn () => (! $this->unit) ? $this->quantity : $this->quantity * $this->unit->conversion_factor,
        );
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
