<?php

namespace App\Queries;

use App\Enums\ChequeStatus;
use App\Filters\ChequeFilter;
use App\Models\Cheque;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ChequeIndexQuery
{
    private const SORTABLE_COLUMNS = ['id', 'amount', 'due', 'reference_number', 'created_at'];

    public function paginated(ChequeFilter $filter, ?string $sortBy, string $sortOrder = 'asc'): LengthAwarePaginator
    {
        return Cheque::with('payee')
            ->filter($filter)
            ->when($sortBy, function ($query) use ($sortBy, $sortOrder) {
                $query->orderBy(
                    in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'due',
                    $sortOrder
                );
            }, function ($query) {
                $query->orderBy('type')
                    ->oldest('due')
                    ->orderBy('created_at');
            })
            ->paginate(10)
            ->withQueryString();
    }

    public function summary(): array
    {
        return [
            'total_receivable' => Cheque::receivable()
                ->whereNotIn('status', [ChequeStatus::Cleared, ChequeStatus::Cancelled])
                ->sum('amount') / 100,
            'total_payable' => Cheque::payable()
                ->whereNotIn('status', [ChequeStatus::Cleared, ChequeStatus::Cancelled])
                ->sum('amount') / 100,
            'overdue_count' => Cheque::overdue()->count(),
        ];
    }
}
