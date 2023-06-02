<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\InvoiceDetails;
use App\Models\Supplier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboradController extends Controller
{
    public function index()
    {
        $totalSales = Cache::remember('total_sales', $seconds = 60 * 60 * 24, function () {
            return InvoiceDetails::whereHas('invoice', fn ($query) => $query->where('invoicable_type', Customer::class))->sum(DB::raw('price * base_quantity'));
        });
        $totalPurchase = Cache::remember('total_purchase', $seconds = 60 * 60 * 24, function () {
            return InvoiceDetails::whereHas('invoice', fn ($query) => $query->where('invoicable_type', Supplier::class))->sum(DB::raw('price * base_quantity'));
        });

        return inertia('Dashboard', [
            'total_sales' => $totalSales,
            'total_purchase' => $totalPurchase,
        ]);
    }
}
