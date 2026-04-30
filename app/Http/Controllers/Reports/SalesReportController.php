<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Queries\Reports\SalesReportQuery;
use App\Services\ReportDateResolver;
use Illuminate\Http\Request;

class SalesReportController extends Controller
{
    public function index(Request $request, SalesReportQuery $query, ReportDateResolver $dateResolver)
    {
        abort_unless(auth()->user()->hasPermission('reports.view'), 403);

        $dates = $dateResolver->resolve($request);
        $groupBy = $request->input('group_by', 'day');

        return inertia('Reports/Sales', [
            'data' => $query->getData($dates['from'], $dates['to'], $groupBy),
            'summary' => $query->getSummary($dates['from'], $dates['to']),
            'filters' => $request->only(['preset', 'from_date', 'to_date', 'customer', 'product', 'payment_method', 'channel', 'group_by']),
            'customers' => fn () => Customer::pluck('name', 'id'),
            'presets' => ReportDateResolver::presets(),
        ]);
    }
}
