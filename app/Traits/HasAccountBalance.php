<?php

namespace App\Traits;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

trait HasAccountBalance
{
    /**
     * Calculate the account balance for the entity.
     */
    public function getAccountBalanceAttribute(): float
    {
        if (array_key_exists('invoiced_total', $this->attributes)
            && array_key_exists('settling_paid', $this->attributes)
            && array_key_exists('reversing_paid', $this->attributes)) {
            $invoicedTotal = (float) ($this->attributes['invoiced_total'] ?? 0);
            $totalPaid = (float) ($this->attributes['settling_paid'] ?? 0) - (float) ($this->attributes['reversing_paid'] ?? 0);

            return $invoicedTotal - $totalPaid + (float) ($this->opening_debit ?? 0) - (float) ($this->opening_credit ?? 0);
        }

        return $this->calculateAccountBalance();
    }

    /**
     * Scope to eager-load account balance aggregates as subselects.
     */
    public function scopeWithAccountBalance(Builder $query): Builder
    {
        $settling = $this->settlingDirection();
        $reversing = $settling === 'in' ? 'out' : 'in';
        $morphType = static::class;
        $table = $this->getTable();

        return $query->addSelect([
            'invoiced_total' => Invoice::query()
                ->whereColumn('invocable_id', "{$table}.id")
                ->where('invocable_type', $morphType)
                ->selectRaw('COALESCE(SUM(total - discount), 0)'),
            'settling_paid' => Payment::query()
                ->whereColumn('payable_id', "{$table}.id")
                ->where('payable_type', $morphType)
                ->where('direction', $settling)
                ->selectRaw('COALESCE(SUM(amount), 0)'),
            'reversing_paid' => Payment::query()
                ->whereColumn('payable_id', "{$table}.id")
                ->where('payable_type', $morphType)
                ->where('direction', $reversing)
                ->selectRaw('COALESCE(SUM(amount), 0)'),
        ]);
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

        return $invoicedTotal - $totalPaid + (float) ($this->opening_debit ?? 0) - (float) ($this->opening_credit ?? 0);
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

        return $directSettling - $directReversing;
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

        return $directSettling - $directReversing;
    }
}
