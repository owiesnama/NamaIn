<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property Pivot $pivot
 */
class Product extends BaseModel
{
    use HasFactory, SoftDeletes;

    /**
     * List of fillable fields of the product
     *
     * @var string[string]
     */
    protected $fillable = ['name', 'cost', 'expire_date'];

    /**
     * List of the attributes to append to the product
     *
     * @var string[string]
     */
    protected $appends = ['expired_at'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expire_date' => 'date',
    ];

    /**
     * The stock details for this product
     */
    public function stock(): BelongsToMany
    {
        return $this->belongsToMany(Storage::class, 'stocks')->withPivot([
            'quantity',
        ])->withTimestamps();
    }

    /**
     * Unites associated with this product
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /**
     * Quantity on hand for this product
     */
    public function quantityOnHand(): int
    {
        return $this->stock->sum('pivot.quantity');
    }

    /**
     * Check whether this product went low on quantity
     *
     * @return bool
     */
    public function isRunningLow()
    {
        return $this->quantityOnHand() <= config('namain.min_qunantity_acceptable');
    }

    /**
     * Get the expiration date formatted
     */
    public function getExpireDateAttribute(): string
    {
        return Carbon::createFromFormat('Y-m-d H:i:s', $this->attributes['expire_date'])->format('Y-m-d');
    }

    /**
     * Get how many days went since the expiration date
     */
    public function getExpiredAtAttribute(): int
    {
        return now()->diffInDays($this->attributes['expire_date'], false);
    }
}
