<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class Stock extends Pivot
{
    use HasFactory;

    protected $appends = ['totalCost'];

    public function getTotalCostAttribute()
    {
        return $this->product ? $this->quantity * $this->product->cost : 0;
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function storage()
    {
        return $this->belongsTo(Stroage::class);
    }
}
