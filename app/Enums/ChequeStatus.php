<?php

namespace App\Enums;

enum ChequeStatus: string
{
    case Drafted = 'drafted';
    case Returned = 'returned';
    case Paid = 'paid';
    case PartialyPaid = 'partialy_paid';

    public static function casesWithLabels()
    {
        return [
            'Drafted' => static::Drafted,
            'Paid' => static::Paid,
            'Partialy Paid' => static::PartialyPaid,
            'Returned' => static::Returned,
        ];
    }
}
