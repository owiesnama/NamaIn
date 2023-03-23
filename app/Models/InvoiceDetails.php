<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class InvoiceDetails extends BaseModel
{
    use HasFactory;

    public $guarded = [];

    public $with = ['product', 'unit'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function getBaseQuantity()
    {
        if (! $this->unit) {
            return $this->quantity;
        }

        return (int) $this->quantity * $this->unit->conversion_factor;
    }
}
