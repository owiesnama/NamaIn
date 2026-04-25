<?php

namespace App\Enums;

enum CustomerAdvanceStatus: string
{
    case Outstanding = 'outstanding';
    case PartiallySettled = 'partially_settled';
    case Settled = 'settled';

    public function label(): string
    {
        return match ($this) {
            self::Outstanding => 'Outstanding',
            self::PartiallySettled => 'Partially Settled',
            self::Settled => 'Settled',
        };
    }
}
