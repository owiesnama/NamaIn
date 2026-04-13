<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Traits\WithTrashScope;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends BaseModel
{
    use SoftDeletes, WithTrashScope;

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
        'currency',
    ];

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            $payment->currency = $payment->currency ?? $payment->invoice?->currency ?? preference('currency', '$');
        });
    }

    /**
     * List of searchable model's relation attributes
     *
     * @var array<string>
     */
    protected array $searchableRelationsAttributes = [
        'invoice.serial_number',
        'invoice.status',
        'invoice.invocable.name',
    ];

    public function scopeSearch($query, $searchTerm = ''): Builder
    {
        $like = config('database.default') === 'pgsql' ? 'ILIKE' : 'LIKE';

        return $query->when($searchTerm,
            fn ($query) => $query->where(function ($query) use ($searchTerm, $like) {
                $query->where('reference', $like, "%{$searchTerm}%")
                    ->orWhere('notes', $like, "%{$searchTerm}%")
                    ->orWhereHas('invoice', function ($query) use ($searchTerm, $like) {
                        $query->where('invoices.serial_number', $like, "%{$searchTerm}%")
                            ->orWhere('invoices.status', $like, "%{$searchTerm}%")
                            ->orWhereHas('invocable', function ($query) use ($searchTerm, $like) {
                                $query->where('name', $like, "%{$searchTerm}%");
                            });
                    });
            })
        );
    }

    /**
     * List of attributes to cast along with what to cast to.
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
