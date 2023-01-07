<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class ItemsWarehouse extends BaseModel
{
    use HasFactory;

    protected $appends = ['totalCost'];

    public function getTotalCostAttribute()
    {
        return  $this->item ? $this->quantity * $this->item->cost : 0 ;
    }

    public function item()
    {
        return $this->belongsTo(\App\Models\Item::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(\App\Models\Warehouse::class);
    }
}
