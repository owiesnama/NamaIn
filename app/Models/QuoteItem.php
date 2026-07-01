<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteItem extends BaseModel
{
    protected $appends = ['line_total'];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    // ── Computed ───────────────────────────────────────────────────────────────

    public function getLineTotalAttribute(): float
    {
        return $this->quantity * (float) $this->unit_price;
    }
}
