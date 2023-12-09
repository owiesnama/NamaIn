<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

<<<<<<<< HEAD:app/Models/Stock.php
class Stock extends Pivot
========

class ProductStorage extends BaseModel
>>>>>>>> master:app/Models/ProductStorage.php
{
    use HasFactory;

    protected $appends = ['totalCost'];

    public function getTotalCostAttribute()
    {
<<<<<<<< HEAD:app/Models/Stock.php
        return  $this->product ? $this->quantity * $this->product->cost : 0;
========
        return  $this->product ? $this->quantity * $this->product->cost : 0 ;
>>>>>>>> master:app/Models/ProductStorage.php
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
