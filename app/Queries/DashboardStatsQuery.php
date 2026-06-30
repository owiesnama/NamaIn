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
    private function cacheKey(string $key): string
    {
        $tenantId = app()->has('currentTenant') ? app('currentTenant')->id : 0;

        return "tenant_{$tenantId}_{$key}";
    }

    public function grossProfit(): float
    {
        return (float) $this->totalSales()
            - (float) $this->totalPurchase()
            - (float) $this->expensesThisMonth()
            + (float) $this->totalInventoryValue();
    }

    public function monthlyStatsCached(): array
    {
        return Cache::remember($this->cacheKey('monthly_stats'), now()->addHour(), fn () => $this->getMonthlyStats());
    }

    public function getMonthlyStats(): array
    {
        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->format('Y-m'));
        $dateFormat = DB::dateFormat('month', 'created_at');
        $expenseDateFormat = DB::dateFormat('month', 'expensed_at');

        $sales = Transaction::delivered(now()->subMonths(6))
            ->forCustomer()
            ->select(DB::raw("$dateFormat as month"), DB::raw('SUM(price * quantity) as total'))
            ->groupBy('month')
            ->pluck('total', 'month');

        $purchases = Transaction::delivered(now()->subMonths(6))
            ->forSupplier()
            ->select(DB::raw("$dateFormat as month"), DB::raw('SUM(price * quantity) as total'))
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
        return Cache::remember($this->cacheKey('total_sales'), $this->cacheTtl('day'), fn () => Transaction::delivered(now()->subDays(30))
            ->forCustomer()
            ->totalValue()
        );
    }

    public function totalInventoryValue(): float|string
    {
        return Cache::remember($this->cacheKey('total_inventory_value'), $this->cacheTtl('hour'), function () {
            $result = DB::selectOne('
                SELECT COALESCE(SUM(s.quantity * COALESCE(p.average_cost, p.cost, 0)), 0) as total_value
                FROM stocks s
                INNER JOIN products p ON p.id = s.product_id
                WHERE p.tenant_id = ?
                AND s.deleted_at IS NULL
            ', [app('currentTenant')->id]);

            return (float) ($result->total_value ?? 0);
        });
    }

    public function totalPurchase(): float|string
    {
        return Cache::remember($this->cacheKey('total_purchase'), $this->cacheTtl('day'), fn () => Transaction::delivered(now()->subDays(30))
            ->forSupplier()
            ->totalValue()
        );
    }

    public function outstandingReceivables(): float|string
    {
        return Cache::remember($this->cacheKey('outstanding_receivables'), $this->cacheTtl('hour'), fn () => Invoice::forCustomer()
            ->outstanding()
            ->sum(DB::raw('(total - discount) - paid_amount'))
        );
    }

    public function paymentsThisMonth(): float|string
    {
        return Cache::remember($this->cacheKey('payments_this_month'), $this->cacheTtl('hour'), fn () => Payment::whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount')
        );
    }

    public function expensesThisMonth(): float|string
    {
        return Cache::remember($this->cacheKey('expenses_this_month'), $this->cacheTtl('hour'), fn () => Expense::where(function ($q) {
            $q->where('status', ExpenseStatus::Approved)
                ->orWhereNull('status');
        })
            ->where('expensed_at', '>', now()->subDays(30))
            ->sum('amount')
        );
    }

    public function outstandingPayables(): float|string
    {
        return Cache::remember($this->cacheKey('outstanding_payables'), $this->cacheTtl('hour'), fn () => Invoice::forSupplier()
            ->outstanding()
            ->sum(DB::raw('(total - discount) - paid_amount'))
        );
    }

    public function upcomingCheques(): Collection
    {
        return Cache::remember($this->cacheKey('upcoming_cheques'), $this->cacheTtl('hour'), fn () => Cheque::whereIn('status', [ChequeStatus::Issued, ChequeStatus::Deposited])
            ->where('due', '<=', now()->addDays(7))
            ->with('payee')
            ->orderBy('due')
            ->limit(10)
            ->get()
        );
    }

    public function recentPayments(): Collection
    {
        return Cache::remember($this->cacheKey('recent_payments'), $this->cacheTtl('hour'), fn () => Payment::with(['invoice.invocable', 'createdBy'])
            ->latest('paid_at')
            ->limit(5)
            ->get()
        );
    }

    public function recentExpenses(): Collection
    {
        return Cache::remember($this->cacheKey('recent_expenses'), $this->cacheTtl('hour'), fn () => Expense::with('createdBy')
            ->where('status', ExpenseStatus::Approved)
            ->latest('expensed_at')
            ->limit(5)
            ->get()
        );
    }

    public function topSellingProducts(): Collection
    {
        return Cache::remember($this->cacheKey('top_products'), $this->cacheTtl('day'), fn () => Transaction::delivered(now()->subDays(30))
            ->forCustomer()
            ->with('product')
            ->select('product_id', DB::raw('SUM(quantity) as total_quantity'), DB::raw('SUM(price * quantity) as total_revenue'))
            ->groupBy('product_id')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get()
        );
    }

    public function topCustomers(): SupportCollection
    {
        return Cache::remember($this->cacheKey('top_customers'), $this->cacheTtl('day'), fn () => Transaction::delivered(now()->subDays(30))
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

        return Cache::remember($this->cacheKey('low_stock_products'), $this->cacheTtl('hour'), fn () => Product::with('stock')
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
        // Only cache in production; outside it (incl. the 'local'-based Cypress
        // env) stats must stay fresh right after data is seeded.
        if (! app()->environment('production')) {
            return 0;
        }

        return match ($duration) {
            'day' => now()->addDay(),
            default => now()->addHour(),
        };
    }
}
