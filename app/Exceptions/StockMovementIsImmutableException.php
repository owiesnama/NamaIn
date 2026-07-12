<?php

namespace App\Exceptions;

use RuntimeException;

class StockMovementIsImmutableException extends RuntimeException
{
    public function __construct(string $operation)
    {
        parent::__construct("Stock movements are append-only and cannot be {$operation}. Record a compensating movement instead.");
    }
}
