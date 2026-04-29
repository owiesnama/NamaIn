<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Queries\Reports\ExpenseSummaryQuery;
use App\Services\ReportDateResolver;
use Illuminate\Http\Request;

class ExpenseSummaryController extends Controller
{
    public function index(Request $request, ExpenseSummaryQuery $query, ReportDateResolver $dateResolver)
    {
        abort_unless(auth()->user()->hasPermission('reports.view'), 403);

        $dates = $dateResolver->resolve($request);

        return inertia('Reports/ExpenseSummary', [
            'data' => $query->getData($dates['from'], $dates['to']),
            'summary' => $query->getSummary($dates['from'], $dates['to']),
            'filters' => $request->only(['preset', 'from_date', 'to_date', 'category']),
            'categories' => fn () => Category::pluck('name', 'id'),
            'presets' => ReportDateResolver::presets(),
        ]);
    }
}
