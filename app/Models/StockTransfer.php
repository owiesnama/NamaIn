<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransfer extends BaseModel
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'transferred_at' => 'datetime',
        ];
    }

    public function fromStorage(): BelongsTo
    {
        return $this->belongsTo(Storage::class, 'from_storage_id');
    }

    public function toStorage(): BelongsTo
    {
        return $this->belongsTo(Storage::class, 'to_storage_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockTransferLine::class);
    }
}
