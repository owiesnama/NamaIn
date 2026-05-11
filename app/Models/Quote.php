<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quote extends BaseModel
{
    use SoftDeletes;

    protected array $searchable = ['number', 'notes'];

    protected $appends = ['subtotal', 'total', 'is_expired'];

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'expires_at' => 'date',
            'discount' => 'decimal:2',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'converted_to_invoice_id');
    }

    // ── Computed attributes ────────────────────────────────────────────────────

    public function getSubtotalAttribute(): float
    {
        return $this->items->sum(fn (QuoteItem $item) => $item->quantity * $item->unit_price);
    }

    public function getTotalAttribute(): float
    {
        return max(0, $this->subtotal - (float) $this->discount);
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->isExpired();
    }

    // ── Methods ────────────────────────────────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function markAsConverted(Invoice $invoice): void
    {
        $this->update([
            'status' => QuoteStatus::Converted,
            'converted_to_invoice_id' => $invoice->id,
        ]);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', QuoteStatus::Active);
    }

    public function scopeConverted($query)
    {
        return $query->where('status', QuoteStatus::Converted);
    }

    public function scopeExpired($query)
    {
        return $query->where('status', QuoteStatus::Expired);
    }
}
