<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Transaction;
use App\Models\Storage;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboradController extends Controller
{
    public function index()
    {
        $totalSales = Cache::remember('total_sales', $seconds = 60 * 60 * 24, function () {
            return Transaction::where('delivered',true)->where('created_at','>',Carbon::now()->subMonth())->whereHas('invoice', fn ($query) => $query->where('invoicable_type', Customer::class))->sum(DB::raw('price * base_quantity'));
        });
        $totalPurchase = Cache::remember('total_purchase', $seconds = 60 * 60 * 24, function () {
            return Transaction::where('delivered',true)->where('created_at','>',Carbon::now()->subMonth())->whereHas('invoice', fn ($query) => $query->where('invoicable_type', Supplier::class))->sum(DB::raw('price * base_quantity'));
        });

        return inertia('Dashboard', [
            'total_sales' => $totalSales,
            'total_purchase' => $totalPurchase,
            'transactions' => $this->transactions()->toArray()
        ]);
    }
    /**
     * transactions are the latest quantities get in/out to stroages
     * 
     */
    public function transactions()
    {
        return Transaction::where('delivered', true)->latest()->limit(10)->get();
    }
}
