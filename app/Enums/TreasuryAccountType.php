<?php

namespace App\Enums;

enum TreasuryAccountType: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case MobileMoney = 'mobile_money';
    case ChequeClearing = 'cheque_clearing';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Bank => 'Bank Account',
            self::MobileMoney => 'Mobile Money',
            self::ChequeClearing => 'Cheques in Hand',
        };
    }

    public static function casesWithLabels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->label() => $case->value])
            ->all();
    }
}
