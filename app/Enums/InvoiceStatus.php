<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Delivered = 'delivered';
    case PartiallyDelivered = 'partially_delivered';
    case Initial = 'initial';

    public static function casesWithLabels(): array
    {
        return [
            'Delivered' => self::Delivered,
            'Partially Delivered' => self::PartiallyDelivered,
            'Initial' => self::Initial,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::Delivered => 'Delivered',
            self::PartiallyDelivered => 'Partially Delivered',
            self::Initial => 'Initial',
        };
    }
}
