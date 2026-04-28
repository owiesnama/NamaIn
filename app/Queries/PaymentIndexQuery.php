<?php

namespace App\Queries;

use App\Enums\PaymentDirection;
use App\Filters\PaymentFilter;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PaymentIndexQuery
{
    private const SORTABLE_COLUMNS = ['id', 'created_at', 'amount'];

    public function paginated(PaymentFilter $filter, ?string $sortBy, string $sortOrder = 'desc'): LengthAwarePaginator
    {
        return $this->baseQuery($filter)
            ->with(['invoice.invocable', 'payable', 'createdBy', 'treasuryAccount'])
            ->when($sortBy, function (Builder $query) use ($sortBy, $sortOrder) {
                $query->orderBy(
                    in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'created_at',
                    $this->sortDirection($sortOrder)
                );
            }, function (Builder $query) {
                $query->latest();
            })
            ->paginate(10)
            ->withQueryString();
    }

    public function summary(PaymentFilter $filter): array
    {
        $baseQuery = $this->baseQuery($filter);

        return [
            'total_in' => (clone $baseQuery)->where('direction', PaymentDirection::In->value)->sum('amount'),
            'total_out' => (clone $baseQuery)->where('direction', PaymentDirection::Out->value)->sum('amount'),
        ];
    }

    private function baseQuery(PaymentFilter $filter): Builder
    {
        return Payment::filter($filter);
    }

    private function sortDirection(string $sortOrder): string
    {
        return strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';
    }
}
