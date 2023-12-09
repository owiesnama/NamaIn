<?php

namespace App\Enums;

enum ChequeStatus: string
{
    case Drafted = 'drafted';
    case Desirved = 'desirved';
    case Returned = 'returned';
    case Paid = 'paid';
    case PartialyPaid = 'partialy_paid';

    public static function casesWithLabels()
    {
        return [
            'Drafted' => self::Drafted,
            'Desirved' => self::Desirved,
            'Paid' => self::Paid,
            'Partialy Paid' => self::PartialyPaid,
            'Returned' => self::Returned,
        ];
    }
}
