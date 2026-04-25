<?php

namespace App\Models;

use App\Enums\CustomerAdvanceStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerAdvance extends BaseModel
{
    use SoftDeletes;

    /**
     * @var array<string>
     */
    protected $appends = ['remaining_balance'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'settled_amount' => 'decimal:2',
            'status' => CustomerAdvanceStatus::class,
            'given_at' => 'datetime',
        ];
    }

    /**
     * Expose remaining balance as a serialised attribute.
     */
    public function getRemainingBalanceAttribute(): float
    {
        return (float) $this->amount - (float) $this->settled_amount;
    }

    /**
     * The customer this advance belongs to.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The treasury account this advance was drawn from.
     */
    public function treasuryAccount(): BelongsTo
    {
        return $this->belongsTo(TreasuryAccount::class);
    }

    /**
     * The user who created this advance.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Payments recorded against this advance (repayments and invoice offsets).
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /**
     * The remaining unsettled balance on this advance.
     */
    public function remainingBalance(): float
    {
        return $this->getRemainingBalanceAttribute();
    }

    /**
     * Whether this advance has been fully settled.
     */
    public function isFullySettled(): bool
    {
        return $this->remainingBalance() <= 0;
    }

    /**
     * Recalculate settled_amount from linked payments and update status accordingly.
     */
    public function updateSettlementStatus(): void
    {
        $totalSettled = (float) $this->payments()->sum('amount');

        $this->settled_amount = $totalSettled;
        $this->status = match (true) {
            $totalSettled <= 0 => CustomerAdvanceStatus::Outstanding,
            $totalSettled < (float) $this->amount => CustomerAdvanceStatus::PartiallySettled,
            default => CustomerAdvanceStatus::Settled,
        };

        $this->save();
    }
}
