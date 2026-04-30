<?php

namespace App\Models;

use App\Enums\TreasuryAccountType;
use App\Enums\TreasuryMovementReason;
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

    /**
     * Total cash received during this session, expressed in integer cents.
     *
     * Uses the treasury_movements table as the authoritative source of cash flow
     * rather than the invoice.payment_method column, which reflects the chosen
     * method at checkout time but not the actual cash that landed in the drawer.
     *
     * Falls back to a scaled invoice-total sum when no cash drawer is configured
     * for the sale point (e.g. during testing or for non-cash-drawer POS setups).
     */
    public function cashSalesTotal(): int
    {
        $cashDrawer = TreasuryAccount::where('sale_point_id', $this->storage_id)
            ->ofType(TreasuryAccountType::Cash)
            ->active()
            ->first();

        if ($cashDrawer) {
            return (int) TreasuryMovement::where('treasury_account_id', $cashDrawer->id)
                ->where('reason', TreasuryMovementReason::PaymentReceived)
                ->where('occurred_at', '>=', $this->created_at)
                ->when($this->closed_at, fn ($q) => $q->where('occurred_at', '<=', $this->closed_at))
                ->sum('amount');
        }

        // Fallback: scale decimal dollar invoice totals to integer cents.
        return (int) round(
            $this->invoices()
                ->where('payment_method', 'cash')
                ->sum('total') * 100
        );
    }

    /**
     * Expected cash in the drawer at close: opening float + all cash received.
     * Both operands are in integer cents.
     */
    public function expectedClosingFloat(): int
    {
        return $this->opening_float + $this->cashSalesTotal();
    }

    /**
     * Difference between actual counted cash and expected cash.
     * Positive = cashier has more than expected (over).
     * Negative = cashier is short (under).
     * Both operands are in integer cents.
     */
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
