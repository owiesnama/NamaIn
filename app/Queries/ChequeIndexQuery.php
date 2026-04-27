<?php

namespace App\Queries;

use App\Enums\ChequeStatus;
use App\Models\Cheque;

class ChequeIndexQuery
{
    public function summary(): array
    {
        return [
            'total_receivable' => Cheque::receivable()
                ->whereNotIn('status', [ChequeStatus::Cleared, ChequeStatus::Cancelled])
                ->sum('amount'),
            'total_payable' => Cheque::payable()
                ->whereNotIn('status', [ChequeStatus::Cleared, ChequeStatus::Cancelled])
                ->sum('amount'),
            'overdue_count' => Cheque::overdue()->count(),
        ];
    }
}
