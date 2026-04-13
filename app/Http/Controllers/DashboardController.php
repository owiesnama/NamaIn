<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Transaction;
use App\Queries\DashboardStatsQuery;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(DashboardStatsQuery $query)
    {
        return inertia('Dashboard', [
            'total_sales' => $this->totalSales(),
            'total_purchase' => $this->totalPurchase(),
            'outstanding_receivables' => $this->outstandingReceivables(),
            'payments_this_month' => $this->paymentsThisMonth(),
            'transactions' => $this->recentTransactions(),
            'recent_payments' => $this->recentPayments(),
            'top_products' => $this->topSellingProducts(),
            'low_stock_products' => $this->lowStockProducts(),
            'monthly_stats' => Cache::remember('monthly_stats', $this->cacheTtl('hour'), fn () => $query->getMonthlyStats()),
        ]);
    }

    private function totalSales(): float|string
    {
        return Cache::remember('total_sales', $this->cacheTtl('day'), fn () => Transaction::delivered(now()->subMonth())
            ->forCustomer()
            ->totalValue()
        );
    }

    private function totalPurchase(): float|string
    {
        return Cache::remember('total_purchase', $this->cacheTtl('day'), fn () => Transaction::delivered(now()->subMonth())
            ->forSupplier()
            ->totalValue()
        );
    }

    private function outstandingReceivables(): float|string
    {
        return Cache::remember('outstanding_receivables', $this->cacheTtl('hour'), fn () => Invoice::forCustomer()
            ->outstanding()
            ->sum(DB::raw('(total - discount) - paid_amount'))
        );
    }

    private function paymentsThisMonth(): float|string
    {
        return Cache::remember('payments_this_month', $this->cacheTtl('hour'), fn () => Payment::whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount')
        );
    }

    private function recentPayments(): Collection
    {
        return Cache::remember('recent_payments', $this->cacheTtl('hour'), fn () => Payment::with(['invoice.invocable', 'createdBy'])
            ->latest('paid_at')
            ->limit(5)
            ->get()
        );
    }

    private function topSellingProducts(): Collection
    {
        return Cache::remember('top_products', $this->cacheTtl('day'), fn () => Transaction::delivered(now()->subMonth())
            ->forCustomer()
            ->select('product_id', DB::raw('SUM(base_quantity) as total_quantity'), DB::raw('SUM(price * base_quantity) as total_revenue'))
            ->groupBy('product_id')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get()
        );
    }

    private function lowStockProducts(): Collection
    {
        $stockSubquery = '(SELECT COALESCE(SUM(quantity), 0) FROM stocks WHERE stocks.product_id = products.id AND stocks.deleted_at IS NULL)';

        return Cache::remember('low_stock_products', $this->cacheTtl('hour'), fn () => Product::with('stock')
            ->whereRaw("$stockSubquery <= COALESCE(products.alert_quantity, ?)", [config('namain.min_quantity_acceptable')])
            ->orderByRaw("$stockSubquery ASC")
            ->limit(5)
            ->get()
        );
    }

    private function recentTransactions(): Collection
    {
        return Transaction::delivered(now()->subMonth())
            ->latest()
            ->limit(10)
            ->get();
    }

    /**
     * Returns 0 in the testing environment to bypass the cache between test runs.
     */
    private function cacheTtl(string $duration): DateTimeInterface|int
    {
        if (app()->environment('testing')) {
            return 0;
        }

        return match ($duration) {
            'day' => now()->addDay(),
            default => now()->addHour(),
        };
    }
}
