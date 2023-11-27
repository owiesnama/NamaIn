<?php

namespace App\Models;

use App\Traits\HasClassAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends BaseModel
{
    use HasFactory, SoftDeletes,HasClassAttributes;

    /**
     * List of attributes that can be mass assigned.
     *
     * @var array<string>
     */
    protected $fillable = ['name', 'phone_number', 'address'];

    /**
     * Cheque wrote to this supplier.
     *
     * @return MorphMany
     */
    public function cheques(): MorphMany
    {
        return $this->morphMany(Cheque::class, 'chequeable');
    }
}
