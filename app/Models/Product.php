<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;


class Product extends BaseModel
{
    use HasFactory;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'expire_date' => 'date',
    ];

    public function purchasements()
    {
        return $this->belongsToMany(Purchasement::class);
    }

}
