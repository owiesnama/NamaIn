<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceDetails;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboradController extends Controller
{
    public function index()
    {
        return inertia('Dashboard',[
            'total_sales' => InvoiceDetails::whereHas('invoice', fn ($query) => $query->where('invoicable_type', Customer::class))->sum(DB::raw('price * base_quantity')),
            'total_purchase' => InvoiceDetails::whereHas('invoice', fn ($query) => $query->where('invoicable_type', Supplier::class))->sum(DB::raw('price * base_quantity')),
        ]);
    }
}
