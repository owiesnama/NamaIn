<?php

namespace App\Queries\Reports;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProfitAndLossQuery extends ReportQuery
{
    public function get(Carbon $from, Carbon $to): array
    {
        return $this->remember(
            "pnl_data_{$from->toDateString()}_{$to->toDateString()}",
            fn () => $this->buildData($from, $to),
        );
    }

    public function summary(Carbon $from, Carbon $to): array
    {
        return $this->remember(
            "pnl_summary_{$from->toDateString()}_{$to->toDateString()}",
            fn () => $this->buildSummary($from, $to),
        );
    }

    private function buildData(Carbon $from, Carbon $to): array
    {
        $dateFormat = $this->dateFormat('month');

        $revenue = Transaction::delivered()
            ->forCustomer()
            ->whereBetween('transactions.created_at', [$from, $to])
            ->select(
                DB::raw("$dateFormat as period"),
                DB::raw('SUM('.Transaction::lineRevenueSql('transactions').') / 100.0 as amount'),
            )
            ->groupBy('period')
            ->pluck('amount', 'period');

        $cogs = Transaction::delivered()
            ->forCustomer()
            ->whereBetween('transactions.created_at', [$from, $to])
            ->select(
                DB::raw("$dateFormat as period"),
                DB::raw('SUM(transactions.base_quantity * COALESCE(transactions.unit_cost, 0)) / 100.0 as amount'),
            )
            ->groupBy('period')
            ->pluck('amount', 'period');

        $expenses = Expense::where('status', ExpenseStatus::Approved)
            ->whereBetween('expensed_at', [$from, $to])
            ->select(
                DB::raw($this->dateFormat('month', 'expensed_at').' as period'),
                DB::raw('SUM(amount) as amount'),
            )
            ->groupBy('period')
            ->pluck('amount', 'period');

        $periods = $revenue->keys()
            ->merge($cogs->keys())
            ->merge($expenses->keys())
            ->unique()
            ->sort()
            ->values();

        return $periods->map(function ($period) use ($revenue, $cogs, $expenses) {
            $rev = (float) ($revenue[$period] ?? 0);
            $cost = (float) ($cogs[$period] ?? 0);
            $exp = (float) ($expenses[$period] ?? 0);
            $grossProfit = $rev - $cost;
            $netProfit = $grossProfit - $exp;

            return [
                'period' => $period,
                'revenue' => round($rev, 2),
                'cogs' => round($cost, 2),
                'gross_profit' => round($grossProfit, 2),
                'expenses' => round($exp, 2),
                'net_profit' => round($netProfit, 2),
            ];
        })->all();
    }

    private function buildSummary(Carbon $from, Carbon $to): array
    {
        $revenue = (float) Transaction::delivered()
            ->forCustomer()
            ->whereBetween('transactions.created_at', [$from, $to])
            ->sum(DB::raw(Transaction::lineRevenueSql('transactions'))) / 100;

        $cogs = (float) Transaction::delivered()
            ->forCustomer()
            ->whereBetween('transactions.created_at', [$from, $to])
            ->sum(DB::raw('transactions.base_quantity * COALESCE(transactions.unit_cost, 0)')) / 100;

        $expenses = (float) Expense::where('status', ExpenseStatus::Approved)
            ->whereBetween('expensed_at', [$from, $to])
            ->sum('amount') / 100;

        $grossProfit = $revenue - $cogs;
        $netProfit = $grossProfit - $expenses;

        $hasProvisionalCosts = Transaction::delivered()
            ->forCustomer()
            ->whereBetween('transactions.created_at', [$from, $to])
            ->where('transactions.cost_provisional', true)
            ->exists();

        return [
            'revenue' => round($revenue, 2),
            'cogs' => round($cogs, 2),
            'gross_profit' => round($grossProfit, 2),
            'gross_margin' => $revenue > 0 ? round(($grossProfit / $revenue) * 100, 1) : 0,
            'expenses' => round($expenses, 2),
            'net_profit' => round($netProfit, 2),
            'net_margin' => $revenue > 0 ? round(($netProfit / $revenue) * 100, 1) : 0,
            'has_provisional_costs' => $hasProvisionalCosts,
        ];
    }
}
