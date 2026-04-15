<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Traits\WithTrashScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends BaseModel
{
    use HasFactory, SoftDeletes, WithTrashScope;

    /**
     * Direct columns to search against.
     *
     * @var array<string>
     */
    protected array $searchable = ['reference', 'notes'];

    /**
     * List of searchable model's relation attributes
     *
     * @var array<string>
     */
    protected array $searchableRelationsAttributes = [
        'invoice.serial_number',
        'invoice.invocable.name',
        'payable.name',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (Payment $payment) {
            $payment->currency = $payment->currency
                ?? $payment->invoice?->currency
                ?? $payment->payable?->currency
                ?? preference('currency', 'SDG');
        });
    }

    /**
     * @var array<string>
     */
    protected $appends = ['paid_at_human', 'created_at_human'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'paid_at' => 'datetime',
            'amount' => 'decimal:2',
            'metadata' => 'json',
        ];
    }

    /**
     * The invoice this payment belongs to.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * The payable entity this payment belongs to.
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The user who created this payment.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get paid_at formatted for humans with locale support.
     */
    public function getPaidAtHumanAttribute(): ?string
    {
        if (! $this->paid_at) {
            return null;
        }

        return $this->paid_at->locale(app()->getLocale())->diffForHumans();
    }
}
