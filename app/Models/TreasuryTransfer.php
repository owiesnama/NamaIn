<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreasuryTransfer extends BaseModel
{
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'transferred_at' => 'datetime',
        ];
    }

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(TreasuryAccount::class, 'from_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(TreasuryAccount::class, 'to_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
