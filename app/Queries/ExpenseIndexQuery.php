<?php

namespace App\Queries;

use App\Filters\ExpenseFilter;
use App\Models\Category;
use App\Models\Expense;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExpenseIndexQuery
{
    /**
     * Returns each expense category that has a budget limit set,
     * alongside how much has been approved and spent this month.
     */
    public function categoryBudgets(): Collection
    {
        return Category::ofType('expense')
            ->whereNotNull('budget_limit')
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'limit' => $category->budget_limit,
                'spent' => $category->expenses()
                    ->whereMonth('expensed_at', now()->month)
                    ->whereYear('expensed_at', now()->year)
                    ->sum('amount'),
            ]);
    }

    /**
     * Returns total approved spending grouped by category,
     * scoped to the active filter.
     */
    public function spendingByCategory(ExpenseFilter $filter): Collection
    {
        return Expense::filter($filter)
            ->join('categorizables', function ($join) {
                $join->on('expenses.id', '=', 'categorizables.categorizable_id')
                    ->where('categorizables.categorizable_type', '=', Expense::class);
            })
            ->join('categories', 'categorizables.category_id', '=', 'categories.id')
            ->groupBy('categories.id', 'categories.name')
            ->select('categories.name', DB::raw('SUM(amount) as total'))
            ->get();
    }
}
