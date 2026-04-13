<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        [$totalSales, $totalPurchase] = collect([Customer::class => 'total_sales', Supplier::class => 'total_purchase'])
            ->map(function ($key, $type) {
                return Cache::remember(
                    $key,
                    now()->addDay(),
                    fn () => Transaction::delivered(now()->subMonth())
                        ->whereHas('invoice', fn ($query) => $query->where('invocable_type', $type))
                        ->sum(DB::raw('price * base_quantity'))
                );
            })->values();

        // Calculate outstanding receivables
        $outstandingReceivables = Cache::remember(
            'outstanding_receivables',
            now()->addHour(),
            fn () => Invoice::where('invocable_type', Customer::class)
                ->whereIn('payment_status', [PaymentStatus::Unpaid, PaymentStatus::PartiallyPaid])
                ->get()
                ->sum('remaining_balance')
        );

        // Calculate payments this month
        $paymentsThisMonth = Cache::remember(
            'payments_this_month',
            now()->addHour(),
            fn () => Payment::whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('amount')
        );

        // Get recent payments
        $recentPayments = Payment::with(['invoice.invocable', 'createdBy'])
            ->latest('paid_at')
            ->limit(5)
            ->get();

        // Top Selling Products
        $topProducts = Cache::remember(
            'top_products',
            app()->environment('testing') ? 0 : now()->addDay(),
            fn () => Transaction::delivered(now()->subMonth())
                ->whereHas('invoice', fn ($query) => $query->where('invocable_type', Customer::class))
                ->select('product_id', DB::raw('SUM(base_quantity) as total_quantity'), DB::raw('SUM(price * base_quantity) as total_revenue'))
                ->groupBy('product_id')
                ->orderByDesc('total_quantity')
                ->limit(5)
                ->with('product')
                ->get()
        );

        // Low Stock Products
        $lowStockProducts = Cache::remember(
            'low_stock_products',
            app()->environment('testing') ? 0 : now()->addHour(),
            fn () => Product::with('stock')
                ->get()
                ->filter(fn ($product) => $product->isRunningLow())
                ->take(5)
        );

        // Monthly stats for chart
        $monthlyStats = Cache::remember(
            'monthly_stats',
            now()->addHour(),
            function () {
                $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->format('Y-m'));

                $driver = DB::getDriverName();
                if ($driver === 'sqlite') {
                    $dateFormat = "strftime('%Y-%m', created_at)";
                } elseif ($driver === 'pgsql') {
                    $dateFormat = "TO_CHAR(created_at, 'YYYY-MM')";
                } else {
                    $dateFormat = "DATE_FORMAT(created_at, '%Y-%m')";
                }

                $sales = Transaction::delivered(now()->subMonths(6))
                    ->whereHas('invoice', fn ($q) => $q->where('invocable_type', Customer::class))
                    ->select(DB::raw("$dateFormat as month"), DB::raw('SUM(price * base_quantity) as total'))
                    ->groupBy('month')
                    ->pluck('total', 'month');

                $purchases = Transaction::delivered(now()->subMonths(6))
                    ->whereHas('invoice', fn ($q) => $q->where('invocable_type', Supplier::class))
                    ->select(DB::raw("$dateFormat as month"), DB::raw('SUM(price * base_quantity) as total'))
                    ->groupBy('month')
                    ->pluck('total', 'month');

                return [
                    'labels' => $months->map(fn ($m) => Carbon::parse($m)->locale(app()->getLocale())->translatedFormat('M Y')),
                    'sales' => $months->map(fn ($m) => $sales->get($m, 0)),
                    'purchases' => $months->map(fn ($m) => $purchases->get($m, 0)),
                ];
            }
        );

        return inertia('Dashboard', [
            'total_sales' => $totalSales,
            'total_purchase' => $totalPurchase,
            'outstanding_receivables' => $outstandingReceivables,
            'payments_this_month' => $paymentsThisMonth,
            'transactions' => $this->transactions()->toArray(),
            'recent_payments' => $recentPayments,
            'top_products' => $topProducts,
            'low_stock_products' => $lowStockProducts->values(),
            'monthly_stats' => $monthlyStats,
        ]);
    }

    public function transactions()
    {
        return Transaction::delivered(now()->subMonth())
            ->latest()
            ->limit(10)
            ->get();
    }
}
