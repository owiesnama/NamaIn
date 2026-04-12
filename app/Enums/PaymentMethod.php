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
            'Cash' => self::Cash,
            'Credit' => self::Credit,
            'Cheque' => self::Cheque,
            'Bank Transfer' => self::BankTransfer,
            'Mixed' => self::Mixed,
        ];
    }

    public function label(): string
    {
        return match($this) {
            self::Cash => 'Cash',
            self::Credit => 'Credit',
            self::Cheque => 'Cheque',
            self::BankTransfer => 'Bank Transfer',
            self::Mixed => 'Mixed',
        };
    }
}
