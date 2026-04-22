<?php

namespace App\Enums;

enum ChequeType: int
{
    case Payable = 0;
    case Receivable = 1;

    public function label(): string
    {
        return match ($this) {
            ChequeType::Payable => 'Payable',
            ChequeType::Receivable => 'Receivable',
        };
    }
}
