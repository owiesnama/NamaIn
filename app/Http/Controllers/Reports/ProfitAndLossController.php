<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Queries\Reports\ProfitAndLossQuery;
use App\Services\ReportDateResolver;
use Illuminate\Http\Request;

class ProfitAndLossController extends Controller
{
    public function index(Request $request, ProfitAndLossQuery $query, ReportDateResolver $dateResolver)
    {
        abort_unless(auth()->user()->hasPermission('reports.view'), 403);

        $dates = $dateResolver->resolve($request);

        return inertia('Reports/ProfitAndLoss', [
            'data' => $query->getData($dates['from'], $dates['to']),
            'summary' => $query->getSummary($dates['from'], $dates['to']),
            'filters' => $request->only(['preset', 'from_date', 'to_date']),
            'presets' => ReportDateResolver::presets(),
        ]);
    }
}
