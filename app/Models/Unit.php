<?php

namespace App\Models;

class Unit extends BaseModel
{
    /**
     * List of attributes that can be massed assigned.
     *
     * @var array<string>
     */
    protected $fillable = ['name', 'conversion_factor'];
}
