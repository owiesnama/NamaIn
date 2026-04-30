<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Queries\DashboardStatsQuery;

class DashboardController extends Controller
{
    public function index(DashboardStatsQuery $query)
    {
        return inertia('Dashboard', [
            'total_sales' => $query->totalSales(),
            'total_purchase' => $query->totalPurchase(),
            'expenses_this_month' => $query->expensesThisMonth(),
            'total_inventory_value' => $query->totalInventoryValue(),
            'outstanding_receivables' => $query->outstandingReceivables(),
            'outstanding_payables' => $query->outstandingPayables(),
            'gross_profit' => $query->grossProfit(),
            'payments_this_month' => $query->paymentsThisMonth(),
            'top_products' => $query->topSellingProducts(),
            'top_customers' => $query->topCustomers(),
            'low_stock_products' => $query->lowStockProducts(),
            'expired_products' => $query->expiredProducts(),
            'upcoming_cheques' => $query->upcomingCheques(),
            'recent_expenses' => $query->recentExpenses(),
            'recent_payments' => $query->recentPayments(),
            'transactions' => $query->recentTransactions(),
            'monthly_stats' => $query->monthlyStatsCached(),
        ]);
    }
}
