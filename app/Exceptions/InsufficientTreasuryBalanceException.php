<?php

namespace App\Exceptions;

use App\Models\TreasuryAccount;
use RuntimeException;

class InsufficientTreasuryBalanceException extends RuntimeException
{
    public function __construct(
        public readonly TreasuryAccount $account,
        public readonly int $requestedAmount,
    ) {
        parent::__construct(
            "Insufficient balance in '{$account->name}'. Requested: {$requestedAmount}, Available: {$account->currentBalance()}"
        );
    }
}
