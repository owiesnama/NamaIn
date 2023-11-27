<?php

namespace App\Models;

use App\Traits\HasClassAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends BaseModel
{
    use HasFactory, SoftDeletes,HasClassAttributes;

    /**
     *  List of attributes that can be mass assigned.
     *
     * @var array<string>
     */
    protected $fillable = ['name', 'address', 'phone_number','type'];

    /**
     * The cheques that belongs to this customer.
     *
     * @return MorphMany
     */
    public function cheques(): MorphMany
    {
        return $this->morphMany(Cheque::class, 'chequeable');
    }
}
