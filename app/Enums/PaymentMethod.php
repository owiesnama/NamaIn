<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Credit = 'credit';
    case Cheque = 'cheque';
    case BankTransfer = 'bank_transfer';
    case Mixed = 'mixed';

    public static function casesWithLabels(): array
    {
        return [
            'Cash' => self::Cash->value,
            'Cheque' => self::Cheque->value,
            'Bank Transfer' => self::BankTransfer->value,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Credit => 'Credit',
            self::Cheque => 'Cheque',
            self::BankTransfer => 'Bank Transfer',
            self::Mixed => 'Mixed',
        };
    }
}
