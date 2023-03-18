<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceDetails extends Model
{
    use HasFactory;

    public $guarded = [];

    public $with = ['product'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
