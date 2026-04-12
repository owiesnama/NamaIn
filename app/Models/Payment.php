<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends BaseModel
{
    use SoftDeletes;

    /**
     * Attributes that can be mass assigned.
     *
     * @var array<string>
     */
    protected $fillable = [
        'invoice_id',
        'amount',
        'payment_method',
        'reference',
        'notes',
        'paid_at',
        'created_by',
    ];

    /**
     * List of attributes to cast along with what to cast to.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'payment_method' => PaymentMethod::class,
        'paid_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    /**
     * The invoice this payment belongs to.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * The user who created this payment.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
