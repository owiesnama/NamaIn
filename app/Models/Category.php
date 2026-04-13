<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Category extends BaseModel
{
    use HasFactory;

    protected $fillable = ['name'];

    public function products(): MorphToMany
    {
        return $this->morphedByMany(Product::class, 'categorizable');
    }
}
