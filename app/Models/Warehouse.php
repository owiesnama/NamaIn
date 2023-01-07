<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Warehouse extends BaseModel
{
    use HasFactory;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'Address',
    ];

    public function stock()
    {
        return $this->hasMany(ItemsWarehouse::class);
    }

    public function addStock($stock)
    {
        $stockItem = $this->stock()->firstOrNew(['item_id' => $stock->item_id]);
        $stockItem->quantity += $stock->quantity;
        $stockItem->save();
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
