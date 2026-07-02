<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosSession extends BaseModel
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
            'opening_float' => 'integer',
            'closing_float' => 'integer',
        ];
    }

    public function storage(): BelongsTo
    {
        return $this->belongsTo(Storage::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function cashSalesTotal(): int
    {
        // Invoice totals and session floats are both stored in minor units.
        return (int) $this->invoices()
            ->where('payment_method', 'cash')
            ->sum('total');
    }

    public function expectedClosingFloat(): int
    {
        return $this->opening_float + $this->cashSalesTotal();
    }

    public function variance(): int
    {
        if ($this->closing_float === null) {
            return 0;
        }

        return $this->closing_float - $this->expectedClosingFloat();
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }
}
