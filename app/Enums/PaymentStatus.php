<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';

    public static function casesWithLabels(): array
    {
        return [
            'Unpaid' => self::Unpaid,
            'Partially Paid' => self::PartiallyPaid,
            'Paid' => self::Paid,
            'Overdue' => self::Overdue,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid',
            self::PartiallyPaid => 'Partially Paid',
            self::Paid => 'Paid',
            self::Overdue => 'Overdue',
        };
    }
}
