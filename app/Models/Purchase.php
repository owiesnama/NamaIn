<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchasement extends BaseModel
{
    use HasFactory;

    protected $searchableRelationsAttributes = [
        'items.name',
        'vendor.name'
    ];
    
    protected $casts = [
        'items' => AsCollection::class
    ];

    public function items()
    {
        return $this->belongsToMany(Item::class,'item_purchasements')->withPivot([
            'quantity',
            'warehouse_id',
        ]);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}
