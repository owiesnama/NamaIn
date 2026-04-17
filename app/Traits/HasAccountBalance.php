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
     * Calculate the total paid amount as of a specific date.
     */
    private function getTotalPaidAsOf(string $asOfDate): float
    {
        $directPayments = (float) $this->payments()
            ->where('paid_at', '<', $asOfDate)
            ->sum('amount');

        $paymentsOnInvoices = (float) Payment::whereHas('invoice', function ($query) {
            $query->where('invocable_id', $this->id)
                ->where('invocable_type', static::class);
        })->where('paid_at', '<', $asOfDate)
            ->sum('amount');

        return $directPayments + $paymentsOnInvoices;
    }

    /**
     * Calculate the current total paid amount.
     */
    private function getCurrentTotalPaid(): float
    {
        $directPayments = (float) $this->payments()->sum('amount');
        $invoicePayments = (float) $this->invoices()->sum('paid_amount');

        return $directPayments + $invoicePayments;
    }
}
