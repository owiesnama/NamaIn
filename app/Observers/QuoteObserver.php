<?php

namespace App\Observers;

use App\Models\Quote;

class QuoteObserver
{
    public function created(Quote $quote): void
    {
        if (empty($quote->number)) {
            $quote->number = 'Q-'.now()->format('y').'-'.$quote->id;
            $quote->saveQuietly();
        }
    }
}
