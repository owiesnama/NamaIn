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
        return Payment::forParty($this)->with('invoice', 'payable')->latest()->get();
    }
}
