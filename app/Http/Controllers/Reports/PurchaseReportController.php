<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Queries\Reports\PurchaseReportQuery;
use App\Services\ReportDateResolver;
use Illuminate\Http\Request;

class PurchaseReportController extends Controller
{
    public function index(Request $request, PurchaseReportQuery $query, ReportDateResolver $dateResolver)
    {
        abort_unless(auth()->user()->hasPermission('reports.view'), 403);

        $dates = $dateResolver->resolve($request);

        return inertia('Reports/Purchase', [
            'data' => $query->getData($dates['from'], $dates['to'], $request->input('group_by', 'day')),
            'summary' => $query->getSummary($dates['from'], $dates['to']),
            'filters' => $request->only(['preset', 'from_date', 'to_date', 'supplier', 'product', 'group_by']),
            'suppliers' => fn () => Supplier::pluck('name', 'id'),
            'presets' => ReportDateResolver::presets(),
        ]);
    }
}
