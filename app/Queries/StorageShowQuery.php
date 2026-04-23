<?php

namespace App\Queries;

use App\Models\Customer;
use App\Models\Storage;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class StorageShowQuery
{
    private Storage $storage;

    public function forStorage(Storage $storage): self
    {
        $this->storage = $storage;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function transactions(array $filters): LengthAwarePaginator
    {
        return $this->baseTransactionQuery($filters)
            ->latest()
            ->with(['invoice.invocable', 'unit', 'product'])
            ->paginate(10)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function salesCount(array $filters): float
    {
        return (float) $this->baseTransactionQuery($filters)
            ->whereHas('invoice', fn ($q) => $q->where('invocable_type', Customer::class))
            ->sum('base_quantity');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function purchasesCount(array $filters): float
    {
        return (float) $this->baseTransactionQuery($filters)
            ->whereHas('invoice', fn ($q) => $q->where('invocable_type', '!=', Customer::class))
            ->sum('base_quantity');
    }

    public function totalStockValue(): float
    {
        $total = 0;
        $this->storage->stock()->get()->each(function ($product) use (&$total) {
            $total += $product->pivot->quantity * $product->average_cost;
        });

        return (float) $total;
    }

    /**
     * @return array{labels: list<string>, sales: list<int|float>, purchases: list<int|float>}
     */
    public function chartData(): array
    {
        $months = collect();
        for ($i = 5; $i >= 0; $i--) {
            $months->push(Carbon::now()->subMonths($i)->format('Y-m'));
        }

        $dateFormat = $this->monthlyDateFormat();

        $chartSales = Transaction::where('storage_id', $this->storage->id)
            ->whereHas('invoice', fn ($q) => $q->where('invocable_type', Customer::class))
            ->where('created_at', '>=', Carbon::now()->subMonths(5)->startOfMonth())
            ->selectRaw("$dateFormat as month, SUM(quantity) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        $chartPurchases = Transaction::where('storage_id', $this->storage->id)
            ->whereHas('invoice', fn ($q) => $q->where('invocable_type', '!=', Customer::class))
            ->where('created_at', '>=', Carbon::now()->subMonths(5)->startOfMonth())
            ->selectRaw("$dateFormat as month, SUM(quantity) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        return [
            'labels' => $months->map(fn ($m) => Carbon::parse($m)->format('M Y'))->toArray(),
            'sales' => $months->map(fn ($m) => $chartSales->get($m, 0))->toArray(),
            'purchases' => $months->map(fn ($m) => $chartPurchases->get($m, 0))->toArray(),
        ];
    }

    private function monthlyDateFormat(): string
    {
        return match (DB::getDriverName()) {
            'sqlite' => "strftime('%Y-%m', created_at)",
            'pgsql' => "TO_CHAR(created_at, 'YYYY-MM')",
            default => "DATE_FORMAT(created_at, '%Y-%m')",
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function baseTransactionQuery(array $filters): Builder
    {
        $query = Transaction::where('storage_id', $this->storage->id);

        if (! empty($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        if (! empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (! empty($filters['type']) && $filters['type'] !== 'All') {
            if ($filters['type'] === 'Sales') {
                $query->whereHas('invoice', fn ($q) => $q->where('invocable_type', Customer::class));
            } elseif ($filters['type'] === 'Purchases') {
                $query->whereHas('invoice', fn ($q) => $q->where('invocable_type', '!=', Customer::class));
            }
        }

        return $query;
    }
}
