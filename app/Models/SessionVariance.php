<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A server-derived session cash variance (Design 04 §1.2, FR-4): an
 * offline-originated `pos_session.close` whose declared closing float disagreed
 * with the drawer's expected balance. Not a syncable BaseModel — cloud-only and
 * derived, carrying a public_id purely so the reconciliation inbox references
 * it. Amounts are signed integer minor units.
 */
class SessionVariance extends Model
{
    use HasFactory, HasPublicId;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expected_amount' => 'integer',
            'declared_amount' => 'integer',
            'variance_amount' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'pos_session_id');
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    public function drawer(): BelongsTo
    {
        return $this->belongsTo(TreasuryAccount::class, 'treasury_account_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
