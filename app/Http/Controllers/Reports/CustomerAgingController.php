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
        abort_unless(auth()->user()->hasPermission('reports.view'), 403);

        $customerId = $request->input('customer') ? (int) $request->input('customer') : null;

        return inertia('Reports/CustomerAging', [
            'data' => $query->getData($customerId),
            'summary' => $query->getSummary($customerId),
            'filters' => $request->only(['customer']),
            'customers' => fn () => Customer::pluck('name', 'id'),
        ]);
    }
}
