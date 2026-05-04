<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Queries\Reports\SalesReportQuery;
use App\Services\DatePreset;
use Illuminate\Http\Request;

class SalesReportController extends Controller
{
    public function index(Request $request, SalesReportQuery $query, DatePreset $datePreset)
    {
        abort_unless(auth()->user()->hasPermission('reports.view'), 403);

        $dates = $datePreset->fromRequest($request);

        $groupBy = $request->input('group_by', 'day');

        return inertia('Reports/Sales', [
            'data' => $query->get($dates['from'], $dates['to'], $groupBy),
            'summary' => $query->summary($dates['from'], $dates['to']),
            'filters' => $request->only(['preset', 'from_date', 'to_date', 'customer', 'product', 'payment_method', 'channel', 'group_by']),
            'customers' => fn () => Customer::pluck('name', 'id'),
            'presets' => DatePreset::presets(),
        ]);
    }
}
