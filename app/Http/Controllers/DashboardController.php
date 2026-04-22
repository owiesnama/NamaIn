<?php

namespace App\Http\Controllers;

use App\Queries\DashboardStatsQuery;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    private function tenantCacheKey(string $key): string
    {
        $tenantId = app()->has('currentTenant') ? app('currentTenant')->id : 0;

        return "tenant_{$tenantId}_{$key}";
    }

    public function index(DashboardStatsQuery $query)
    {
        $totalSales = $query->totalSales();
        $totalPurchase = $query->totalPurchase();
        $expensesThisMonth = $query->expensesThisMonth();
        $totalInventoryValue = $query->totalInventoryValue();

        return inertia('Dashboard', [
            'total_sales' => $totalSales,
            'total_purchase' => $totalPurchase,
            'expenses_this_month' => $expensesThisMonth,
            'total_inventory_value' => $totalInventoryValue,
            'outstanding_receivables' => $query->outstandingReceivables(),
            'outstanding_payables' => $query->outstandingPayables(),
            'gross_profit' => (float) $totalSales - (float) $totalPurchase - (float) $expensesThisMonth + (float) $totalInventoryValue,
            'payments_this_month' => $query->paymentsThisMonth(),
            'top_products' => $query->topSellingProducts(),
            'top_customers' => $query->topCustomers(),
            'low_stock_products' => $query->lowStockProducts(),
            'upcoming_cheques' => $query->upcomingCheques(),
            'recent_expenses' => $query->recentExpenses(),
            'recent_payments' => $query->recentPayments(),
            'transactions' => $query->recentTransactions(),
            'monthly_stats' => Cache::remember($this->tenantCacheKey('monthly_stats'), now()->addHour(), fn () => $query->getMonthlyStats()),
        ]);
    }
}
