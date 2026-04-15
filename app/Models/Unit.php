<?php

namespace App\Models;

use App\Traits\WithTrashScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends BaseModel
{
    use HasFactory, SoftDeletes, WithTrashScope;

    /**
     * List of attributes that can be massed assigned.
     *
     * @var array<string>
     */

    /**
     * The product associated with this unit.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @var array<string>
     */
    protected $appends = ['created_at_human'];
}
