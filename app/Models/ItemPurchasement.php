<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class ItemPurchasement extends BaseModel
{
    use HasFactory;

    public function stroage()
    {
        return $this->belongsTo(Stroage::class);
    }
}
