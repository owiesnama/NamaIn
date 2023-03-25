<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Customer extends BaseModel
{
    use HasFactory;

    protected $fillable = ['name', 'address', 'phone'];

    public function cheques(): MorphMany
    {
        return $this->morphMany(Cheque::class, 'chequeable');
    }
}
