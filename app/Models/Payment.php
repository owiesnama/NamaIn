<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Traits\WithTrashScope;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends BaseModel
{
    use HasFactory, SoftDeletes, WithTrashScope;

    /**
     * Attributes that can be mass assigned.
     *
     * @var array<string>
     */
    protected $fillable = [
        'invoice_id',
        'payable_id',
        'payable_type',
        'amount',
        'payment_method',
        'reference',
        'notes',
        'paid_at',
        'created_by',
        'currency',
        'metadata',
        'receipt_path',
    ];

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            $payment->currency = $payment->currency
                ?? $payment->invoice?->currency
                ?? $payment->payable?->currency
                ?? preference('currency', 'USD');
        });
    }

    public function scopeSearch($query, $searchTerm = ''): Builder
    {
        $like = config('database.default') === 'pgsql' ? 'ILIKE' : 'LIKE';

        return $query->when($searchTerm,
            fn ($query) => $query->where(function ($query) use ($searchTerm, $like) {
                $query->where('payments.reference', $like, "%{$searchTerm}%")
                    ->orWhere('payments.notes', $like, "%{$searchTerm}%")
                    ->orWhereHas('invoice', function ($query) use ($searchTerm, $like) {
                        $query->where('invoices.serial_number', $like, "%{$searchTerm}%");
                    })
                    ->orWhereHas('invoice', function ($query) use ($searchTerm, $like) {
                        $query->whereHas('invocable', function ($query) use ($searchTerm, $like) {
                            $query->where('name', $like, "%{$searchTerm}%");
                        });
                    })
                    ->orWhereHas('payable', function ($query) use ($searchTerm, $like) {
                        $query->where('name', $like, "%{$searchTerm}%");
                    });
            })
        );
    }

    /**
     * @var array<string>
     */
    protected $appends = ['paid_at_human'];

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
