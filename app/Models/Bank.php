<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bank extends BaseModel
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::unguard();
    }

    /**
     * @var array<string>
     */
    protected $appends = ['created_at_human'];

    public function cheques(): HasMany
    {
        return $this->hasMany(Cheque::class);
    }

    /**
     * The treasury account linked to this bank institution.
     */
    public function treasuryAccount(): HasOne
    {
        return $this->hasOne(TreasuryAccount::class);
    }
}
