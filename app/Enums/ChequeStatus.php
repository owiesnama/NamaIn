<?php

namespace App\Enums;

enum ChequeStatus: string
{
    case Drafted = 'drafted';
    case Issued = 'issued';
    case Deposited = 'deposited';
    case Cleared = 'cleared';
    case PartiallyCleared = 'partially_cleared';
    case Returned = 'returned';
    case Cancelled = 'cancelled';

    public static function casesWithLabels()
    {
        return [
            'Drafted' => self::Drafted,
            'Issued' => self::Issued,
            'Deposited' => self::Deposited,
            'Cleared' => self::Cleared,
            'Partially Cleared' => self::PartiallyCleared,
            'Returned' => self::Returned,
            'Cancelled' => self::Cancelled,
        ];
    }
}
