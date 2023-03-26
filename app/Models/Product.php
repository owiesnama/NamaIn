<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends BaseModel
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'expire_date' => 'date',
    ];

    protected $appends = ['expired_at'];

    public function units()
    {
        return $this->hasMany(Unit::class);
    }

    public function getExpireDateAttribute()
    {
        return Carbon::createFromFormat('Y-m-d H:i:s', $this->attributes['expire_date'])->format('Y-m-d');
    }

    public function getExpiredAtAttribute()
    {
        return now()->diffInDays($this->attributes['expire_date'], false);
    }
}
