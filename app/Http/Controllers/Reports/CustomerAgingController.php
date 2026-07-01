<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Queries\Reports\CustomerAgingQuery;
use Illuminate\Http\Request;

class CustomerAgingController extends Controller
{
    public function index(Request $request, CustomerAgingQuery $query)
    {
        $customerId = $request->input('customer') ? (int) $request->input('customer') : null;

        return inertia('Reports/CustomerAging', [
            'data' => $query->get($customerId),
            'summary' => $query->summary($customerId),
            'filters' => $request->only(['customer']),
            'customers' => fn () => Customer::pluck('name', 'id'),
        ]);
    }
}
