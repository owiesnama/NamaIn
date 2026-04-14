<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bank extends BaseModel
{
    use SoftDeletes;

    protected $fillable = ['name', 'code'];

    /**
     * @var array<string>
     */
    protected $appends = ['created_at_human'];

    public function cheques(): HasMany
    {
        return $this->hasMany(Cheque::class);
    }
}
