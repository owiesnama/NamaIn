<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Unit extends BaseModel
{
    use HasFactory;

    /**
     * List of attributes that can be massed assigned.
     *
     * @var array<string>
     */
    protected $fillable = ['name', 'conversion_factor'];
}
