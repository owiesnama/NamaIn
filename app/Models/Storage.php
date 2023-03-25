<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Storage extends BaseModel
{
    use HasFactory, SoftDeletes;

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
        return $this->belongsToMany(Product::class)->withPivot([
            'quantity',
        ])->withTimestamps();
    }

    public function addStock($attributes)
    {
        $stock = $this->stock()->find($attributes['product']);
        if ($stock) {
            return $stock->pivot->increment('quantity', $attributes['quantity']);
        }

        return $this->stock()->attach([$attributes['product'] => ['quantity' => $attributes['quantity']]]);
    }

    public function toSearchableArray()
    {
        return [
            'title' => $this->name,
            'address' => $this->address,
        ];
    }
}
