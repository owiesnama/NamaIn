<?php

namespace App\Exceptions;

use App\Models\CustomerAdvance;
use RuntimeException;

class OverSettlementException extends RuntimeException
{
    public function __construct(
        public readonly CustomerAdvance $advance,
        public readonly float $requestedAmount,
    ) {
        parent::__construct(
            "Settlement amount {$requestedAmount} exceeds remaining balance {$advance->remainingBalance()} on advance #{$advance->id}."
        );
    }
}
