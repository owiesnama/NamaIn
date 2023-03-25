<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends BaseModel
{
    use HasFactory, SoftDeletes;

    public function cheques(): MorphMany
    {
        return $this->morphMany(Cheque::class, 'chequeable');
    }
}
