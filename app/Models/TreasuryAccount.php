<?php

namespace App\Models;

use App\Enums\TreasuryAccountType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TreasuryAccount extends BaseModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => TreasuryAccountType::class,
            'is_active' => 'boolean',
            'opening_balance' => 'integer',
        ];
    }

    public function storage(): BelongsTo
    {
        return $this->belongsTo(Storage::class, 'sale_point_id');
    }

    /**
     * The bank institution this account belongs to (type = bank only).
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(TreasuryMovement::class);
    }

    public function currentBalance(): int
    {
        return $this->opening_balance + (int) $this->movements()->sum('amount');
    }

    public function isCashDrawer(): bool
    {
        return $this->type === TreasuryAccountType::Cash
            && $this->sale_point_id !== null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeShared(Builder $query): Builder
    {
        return $query->whereNull('sale_point_id');
    }

    public function scopeForSalePoint(Builder $query, int $salePointId): Builder
    {
        return $query->where('sale_point_id', $salePointId);
    }

    public function scopeOfType(Builder $query, TreasuryAccountType $type): Builder
    {
        return $query->where('type', $type);
    }
}
