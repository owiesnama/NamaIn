<?php

namespace App\Models;

use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Traits\WithTrashScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
            'direction' => PaymentDirection::class,
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
     * The treasury account that received this payment.
     */
    public function treasuryAccount(): BelongsTo
    {
        return $this->belongsTo(TreasuryAccount::class);
    }

    /**
     * Payments belonging to the given party, whether recorded against one of
     * its invoices or directly against the party itself.
     */
    public function scopeForParty(Builder $query, Model $party): Builder
    {
        return $query->where(function (Builder $query) use ($party) {
            $query->whereHas('invoice', fn (Builder $invoices) => $invoices
                ->where('invocable_id', $party->id)
                ->where('invocable_type', $party::class))
                ->orWhere(fn (Builder $direct) => $direct
                    ->where('payable_id', $party->id)
                    ->where('payable_type', $party::class));
        });
    }

    /**
     * Whether this payment is incoming (money received).
     */
    public function isIncoming(): bool
    {
        return $this->direction === PaymentDirection::In;
    }

    /**
     * Whether this payment is outgoing (money paid out).
     */
    public function isOutgoing(): bool
    {
        return $this->direction === PaymentDirection::Out;
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
