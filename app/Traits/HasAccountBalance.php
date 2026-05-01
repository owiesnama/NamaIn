<?php

namespace App\Traits;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;

trait HasAccountBalance
{
    /**
     * Calculate the account balance for the entity.
     */
    public function getAccountBalanceAttribute(): float
    {
        return $this->calculateAccountBalance();
    }

    /**
     * Calculate the total account balance (unpaid invoices and direct payments).
     * If $asOfDate is provided, calculate balance as of that date.
     */
    public function calculateAccountBalance(?string $asOfDate = null): float
    {
        $invoicedTotal = (float) $this->invoices()
            ->when($asOfDate, fn ($query) => $query->where('created_at', '<', $asOfDate))
            ->sum(DB::raw('total - discount'));

        $totalPaid = $asOfDate
            ? $this->getTotalPaidAsOf($asOfDate)
            : $this->getCurrentTotalPaid();

        return $invoicedTotal - $totalPaid - (float) ($this->opening_balance ?? 0);
    }

    /**
     * The direction value whose payments reduce this party's balance.
     * Customers default to 'in' (money coming in settles their debt).
     * Suppliers override to 'out' (money going out settles their debt).
     */
    protected function settlingDirection(): string
    {
        return 'in';
    }

    /**
     * Calculate the total paid amount as of a specific date.
     */
    private function getTotalPaidAsOf(string $asOfDate): float
    {
        $settling = $this->settlingDirection();
        $reversing = $settling === 'in' ? 'out' : 'in';

        $directSettling = (float) $this->payments()
            ->where('direction', $settling)
            ->where('paid_at', '<', $asOfDate)
            ->sum('amount');

        $directReversing = (float) $this->payments()
            ->where('direction', $reversing)
            ->where('paid_at', '<', $asOfDate)
            ->sum('amount');

        $paymentsOnInvoices = (float) Payment::whereHas('invoice', function ($query) {
            $query->where('invocable_id', $this->id)
                ->where('invocable_type', static::class);
        })->where('paid_at', '<', $asOfDate)
            ->sum('amount');

        return ($directSettling + $paymentsOnInvoices) - $directReversing;
    }

    /**
     * Calculate the current total paid amount.
     */
    private function getCurrentTotalPaid(): float
    {
        $settling = $this->settlingDirection();
        $reversing = $settling === 'in' ? 'out' : 'in';

        $directSettling = (float) $this->payments()->where('direction', $settling)->sum('amount');
        $directReversing = (float) $this->payments()->where('direction', $reversing)->sum('amount');
        $invoicePayments = (float) $this->invoices()->sum('paid_amount');

        return ($directSettling + $invoicePayments) - $directReversing;
    }
}
