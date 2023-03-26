<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'address', 'phone_number'];

    public function cheques(): MorphMany
    {
        return $this->morphMany(Cheque::class, 'chequeable');
    }
}
