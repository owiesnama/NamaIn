<?php

namespace App\Enums;

/**
 * How a POS checkout treats insufficient stock (Design 03 §5.2).
 *
 * Strict throws InsufficientStockException (cloud web); AllowNegative
 * force-deducts so the sale is never blocked (push replay and the local
 * runtime, where the server records the oversell instead).
 */
enum StockPolicy: string
{
    case Strict = 'strict';
    case AllowNegative = 'allow_negative';
}
