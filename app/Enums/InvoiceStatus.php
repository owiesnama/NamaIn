<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Delivered = 'delivered';
    case PartiallyDelivered = 'partially_delivered';
    case Initial = 'initial';

    public static function casesWithLabels()
    {
        return [
            'Delivered' => self::Delivered,
            'Partially Delivered' => self::PartiallyDelivered,
            'Initial' => self::Initial,
        ];
    }
}
