<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends BaseModel
{
    use HasFactory, SoftDeletes;

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
