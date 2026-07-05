<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A server-derived credit-breach record (Design 02 §6.2): a credit sale pushed
 * the customer's balance past their cached limit. Not a syncable BaseModel — it
 * is cloud-only (never pulled to a device in MVP) and derived, not pushed; it
 * carries a public_id purely so PRD-04's reconciliation inbox can reference it.
 * `credit_limit` and `balance_after` are stored in integer minor units.
 */
class CreditBreachFlag extends Model
{
    use HasPublicId;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'integer',
            'balance_after' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
