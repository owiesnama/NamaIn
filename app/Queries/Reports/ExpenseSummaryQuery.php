<?php

namespace App\Queries\Reports;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ExpenseSummaryQuery
{
    use ResolvesReportDates;

    public function getData(Carbon $from, Carbon $to): array
    {
        return Cache::remember(
            $this->cacheKey("expense_summary_data_{$from->toDateString()}_{$to->toDateString()}"),
            $this->cacheTtl(),
            fn () => $this->buildData($from, $to),
        );
    }

    public function getSummary(Carbon $from, Carbon $to): array
    {
        return Cache::remember(
            $this->cacheKey("expense_summary_{$from->toDateString()}_{$to->toDateString()}"),
            $this->cacheTtl(),
            fn () => $this->buildSummary($from, $to),
        );
    }

    private function buildData(Carbon $from, Carbon $to): array
    {
        return DB::table('expenses')
            ->leftJoin('categorizables', function ($join) {
                $join->on('categorizables.categorizable_id', '=', 'expenses.id')
                    ->where('categorizables.categorizable_type', Expense::class);
            })
            ->leftJoin('categories', 'categorizables.category_id', '=', 'categories.id')
            ->where('expenses.status', ExpenseStatus::Approved)
            ->whereBetween('expenses.expensed_at', [$from, $to])
            ->where('expenses.tenant_id', app()->has('currentTenant') ? app('currentTenant')->id : 0)
            ->whereNull('expenses.deleted_at')
            ->select(
                'categories.id as category_id',
                DB::raw("COALESCE(categories.name, 'Uncategorized') as category_name"),
                DB::raw('COALESCE(categories.budget_limit, 0) as budget_limit'),
                DB::raw('SUM(expenses.amount) as total_spent'),
                DB::raw('COUNT(expenses.id) as expense_count'),
            )
            ->groupBy('categories.id', 'categories.name', 'categories.budget_limit')
            ->orderByDesc('total_spent')
            ->get()
            ->map(fn ($row) => [
                'category_id' => $row->category_id,
                'category_name' => $row->category_name,
                'total_spent' => round((float) $row->total_spent, 2),
                'budget_limit' => round((float) $row->budget_limit, 2),
                'variance' => round((float) $row->budget_limit - (float) $row->total_spent, 2),
                'expense_count' => (int) $row->expense_count,
            ])
            ->all();
    }

    private function buildSummary(Carbon $from, Carbon $to): array
    {
        $data = $this->buildData($from, $to);

        return [
            'total_spent' => round(array_sum(array_column($data, 'total_spent')), 2),
            'total_budget' => round(array_sum(array_column($data, 'budget_limit')), 2),
            'total_variance' => round(array_sum(array_column($data, 'variance')), 2),
            'category_count' => count($data),
            'total_expenses' => array_sum(array_column($data, 'expense_count')),
        ];
    }
}
