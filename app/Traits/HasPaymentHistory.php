<?php

namespace App\Traits;

use App\Models\Payment;
use Illuminate\Support\Collection;

trait HasPaymentHistory
{
    /**
     * Get payment history for this Instance.
     */
    public function getPaymentHistory(): Collection
    {
        return Payment::where(function ($query) {
            $query->whereHas('invoice', function ($query) {
                $query->where('invocable_id', $this->id)
                    ->where('invocable_type', self::class);
            })->orWhere(function ($query) {
                $query->where('payable_id', $this->id)
                    ->where('payable_type', self::class);
            });
        })->with('invoice', 'payable')->latest()->get();
    }
}

/**
 * Get payment history for this customer.
 */
