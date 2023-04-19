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
            'Delivered' => static::Delivered,
            'Partially Delivered' => static::PartiallyDelivered,
            'Initial' => static::Initial,
        ];
    }
}
