<?php

namespace App\Models;

use App\Traits\WithTrashScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends BaseModel
{
    use HasFactory, SoftDeletes, WithTrashScope;

    /**
     * List of attributes that can be massed assigned.
     *
     * @var array<string>
     */
    protected $fillable = ['name', 'conversion_factor'];
}
