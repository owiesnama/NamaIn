<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A server-derived oversell record (Design 02 §6.1): stock was force-deducted
 * past on-hand on a replayed sale. Not a syncable BaseModel — it is cloud-only
 * (never pulled to a device in MVP) and derived, not pushed; it carries a
 * public_id purely so PRD-04's reconciliation inbox can reference it.
 */
class OversellReconciliation extends Model
{
    use HasPublicId;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'oversold_qty' => 'integer',
            'on_hand_before' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
