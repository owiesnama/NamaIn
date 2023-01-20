<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchasement extends BaseModel
{
    use HasFactory;

    protected $searchableRelationsAttributes = [
        'products.name',
        'vendor.name'
    ];
    
    protected $casts = [
        'products' => AsCollection::class
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class,'product_purchase')->withPivot([
            'quantity',
            'storage_id',
        ]);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}
