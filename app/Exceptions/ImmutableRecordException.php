<?php

namespace App\Exceptions;

use RuntimeException;

class ImmutableRecordException extends RuntimeException
{
    public function __construct(string $record, string $operation)
    {
        parent::__construct("{$record} records are append-only and cannot be {$operation}.");
    }
}
