<?php

namespace App\Http\Controllers;

use App\Models\Customer;
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

        return inertia('Dashboard', [
            'total_sales' => $totalSales,
            'total_purchase' => $totalPurchase,
            'transactions' => $this->transactions()->toArray(),
        ]);
    }

    public function transactions()
    {
        return Transaction::delivered(now()->subMonth())->latest()->limit(10)->get();
    }
}
