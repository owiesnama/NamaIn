<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Storage extends BaseModel
{
    use HasFactory;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'address',
    ];

    public function stock()
    {
        return $this->hasMany(ProductStorage::class);
    }

    public function addStock($stock)
    {
        $product = $this->stock()->firstOrNew(['product_id' => $stock->product_id]);
        $product->quantity += $stock->quantity;
        $product->save();
        return $this;
    }
    public function toSearchableArray()
    {
        return [
            'title' => $this->name,
            'address' => $this->address,
        ];
    }
}
