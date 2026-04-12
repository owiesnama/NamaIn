<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\Transaction;
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

        return inertia('Dashboard', [
            'total_sales' => $totalSales,
            'total_purchase' => $totalPurchase,
            'outstanding_receivables' => $outstandingReceivables,
            'payments_this_month' => $paymentsThisMonth,
            'transactions' => $this->transactions()->toArray(),
            'recent_payments' => $recentPayments,
        ]);
    }

    public function transactions()
    {
        return Transaction::delivered(now()->subMonth())->latest()->limit(10)->get();
    }
}
