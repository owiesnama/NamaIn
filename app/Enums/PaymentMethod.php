<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Credit = 'credit';
    case Cheque = 'cheque';
    case BankTransfer = 'bank_transfer';
    case Mixed = 'mixed';
    case Advance = 'advance';

    public static function casesWithLabels(): array
    {
        return [
            'Cash' => self::Cash,
            'Cheque' => self::Cheque,
            'Bank Transfer' => self::BankTransfer,
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
            self::Advance => 'Advance',
        };
    }
}
