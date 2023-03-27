<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name','cost','expire_date'];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'expire_date' => 'date',
    ];

    public function stock()
    {
        return $this->belongsToMany(Storage::class)->withPivot([
            'quantity',
        ])->withTimestamps();
    }

    public function units()
    {
        return $this->hasMany(Unit::class);
    }

    public function quantityOnHand()
    {
        return $this->stock->sum('pivot.quantity');
    }

    public function isRunningLow()
    {
        return $this->quantityOnHand() <= config('namain.min_qunantity_acceptable');
    }
}
