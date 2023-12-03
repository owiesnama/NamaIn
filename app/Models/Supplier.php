<?php

namespace App\Models;

use App\Traits\HasClassAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends BaseModel
{
    use HasClassAttributes, HasFactory, SoftDeletes;

    /**
     * List of attributes that can be mass assigned.
     *
     * @var array<string>
     */
    protected $fillable = ['name', 'phone_number', 'address'];

    /**
     * Cheque wrote to this supplier.
     */
    public function cheques(): MorphMany
    {
        return $this->morphMany(Cheque::class, 'chequeable');
    }

    /**
     * Invoices issued for this supplier
     */
    public function invoices(): MorphMany
    {
        return $this->morphMany(Invoice::class, 'invocable');
    }
}
