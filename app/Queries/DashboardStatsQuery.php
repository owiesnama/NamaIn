<?php

namespace App\Queries;

use App\Enums\ChequeStatus;
use App\Enums\ExpenseStatus;
use App\Models\Cheque;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Transaction;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardStatsQuery
{
    public function getMonthlyStats(): array
    {
        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->format('Y-m'));
        $dateFormat = $this->monthlyDateFormat();
        $expenseDateFormat = $this->monthlyDateFormat('expensed_at');

        $sales = Transaction::delivered(now()->subMonths(6))
            ->forCustomer()
            ->select(DB::raw("$dateFormat as month"), DB::raw('SUM(price * base_quantity) as total'))
            ->groupBy('month')
            ->pluck('total', 'month');

        $purchases = Transaction::delivered(now()->subMonths(6))
            ->forSupplier()
            ->select(DB::raw("$dateFormat as month"), DB::raw('SUM(price * base_quantity) as total'))
            ->groupBy('month')
            ->pluck('total', 'month');

        $expenses = Expense::where('status', ExpenseStatus::Approved)
            ->where('expensed_at', '>=', now()->subMonths(6)->startOfMonth())
            ->select(DB::raw("$expenseDateFormat as month"), DB::raw('SUM(amount) as total'))
            ->groupBy('month')
            ->pluck('total', 'month');

        return [
            'labels' => $months->map(fn ($m) => Carbon::parse($m)->locale(app()->getLocale())->translatedFormat('M Y')),
            'sales' => $months->map(fn ($m) => $sales->get($m, 0)),
            'purchases' => $months->map(fn ($m) => $purchases->get($m, 0)),
            'expenses' => $months->map(fn ($m) => $expenses->get($m, 0)),
        ];
    }

    public function totalSales(): float|string
    {
        return Cache::remember('total_sales', $this->cacheTtl('day'), fn () => Transaction::delivered(now()->subDays(30))
            ->forCustomer()
            ->totalValue()
        );
    }

    public function totalPurchase(): float|string
    {
        return Cache::remember('total_purchase', $this->cacheTtl('day'), fn () => Transaction::delivered(now()->subDays(30))
            ->forSupplier()
            ->totalValue()
        );
    }

    public function outstandingReceivables(): float|string
    {
        return Cache::remember('outstanding_receivables', $this->cacheTtl('hour'), fn () => Invoice::forCustomer()
            ->outstanding()
            ->sum(DB::raw('(total - discount) - paid_amount'))
        );
    }

    public function paymentsThisMonth(): float|string
    {
        return Cache::remember('payments_this_month', $this->cacheTtl('hour'), fn () => Payment::whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount')
        );
    }

    public function expensesThisMonth(): float|string
    {
        return Cache::remember('expenses_this_month', $this->cacheTtl('hour'), fn () => Expense::where(function ($q) {
            $q->where('status', ExpenseStatus::Approved)
                ->orWhereNull('status');
        })
            ->where('expensed_at', '>', now()->subDays(30))
            ->sum('amount')
        );
    }

    public function outstandingPayables(): float|string
    {
        return Cache::remember('outstanding_payables', $this->cacheTtl('hour'), fn () => Invoice::forSupplier()
            ->outstanding()
            ->sum(DB::raw('(total - discount) - paid_amount'))
        );
    }

    public function upcomingCheques(): Collection
    {
        return Cache::remember('upcoming_cheques', $this->cacheTtl('hour'), fn () => Cheque::whereIn('status', [ChequeStatus::Issued, ChequeStatus::Deposited])
            ->where('due', '<=', now()->addDays(7))
            ->with('payee')
            ->orderBy('due')
            ->limit(10)
            ->get()
        );
    }

    public function recentPayments(): Collection
    {
        return Cache::remember('recent_payments', $this->cacheTtl('hour'), fn () => Payment::with(['invoice.invocable', 'createdBy'])
            ->latest('paid_at')
            ->limit(5)
            ->get()
        );
    }

    public function recentExpenses(): Collection
    {
        return Cache::remember('recent_expenses', $this->cacheTtl('hour'), fn () => Expense::with('createdBy')
            ->where('status', ExpenseStatus::Approved)
            ->latest('expensed_at')
            ->limit(5)
            ->get()
        );
    }

    public function topSellingProducts(): Collection
    {
        return Cache::remember('top_products', $this->cacheTtl('day'), fn () => Transaction::delivered(now()->subDays(30))
            ->forCustomer()
            ->with('product')
            ->select('product_id', DB::raw('SUM(base_quantity) as total_quantity'), DB::raw('SUM(price * base_quantity) as total_revenue'))
            ->groupBy('product_id')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get()
        );
    }

    public function topCustomers(): SupportCollection
    {
        return Cache::remember('top_customers', $this->cacheTtl('day'), fn () => Transaction::delivered(now()->subDays(30))
            ->forCustomer()
            ->join('invoices', 'transactions.invoice_id', '=', 'invoices.id')
            ->join('customers', 'invoices.invocable_id', '=', 'customers.id')
            ->where('invoices.invocable_type', Customer::class)
            ->select('customers.name', DB::raw('SUM(transactions.price * transactions.base_quantity) as total_revenue'))
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get()
            ->map(fn ($customer) => [
                'name' => $customer->name,
                'revenue' => (float) $customer->total_revenue,
            ])
        );
    }

    public function lowStockProducts(): Collection
    {
        $stockSubquery = '(SELECT COALESCE(SUM(quantity), 0) FROM stocks WHERE stocks.product_id = products.id AND stocks.deleted_at IS NULL)';

        return Cache::remember('low_stock_products', $this->cacheTtl('hour'), fn () => Product::with('stock')
            ->whereRaw("$stockSubquery <= COALESCE(products.alert_quantity, ?)", [config('namain.min_quantity_acceptable')])
            ->orderByRaw("$stockSubquery ASC")
            ->limit(5)
            ->get()
        );
    }

    public function recentTransactions(): Collection
    {
        return Transaction::delivered(now()->subDays(30))
            ->with(['product', 'unit', 'storage'])
            ->select('transactions.*')
            ->latest()
            ->limit(10)
            ->get();
    }

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

    private function monthlyDateFormat(string $column = 'created_at'): string
    {
        return match (DB::getDriverName()) {
            'sqlite' => "strftime('%Y-%m', $column)",
            'pgsql' => "TO_CHAR($column, 'YYYY-MM')",
            default => "DATE_FORMAT($column, '%Y-%m')",
        };
    }
}
