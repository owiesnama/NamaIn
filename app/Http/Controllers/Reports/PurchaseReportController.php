<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Queries\Reports\PurchaseReportQuery;
use App\Services\Utils\DatePreset;
use Illuminate\Http\Request;

class PurchaseReportController extends Controller
{
    public function index(Request $request, PurchaseReportQuery $query, DatePreset $dateResolver)
    {
        $dates = $dateResolver->fromRequest($request);

        return inertia('Reports/Purchase', [
            'data' => $query->get($dates['from'], $dates['to'], $request->input('group_by', 'day')),
            'summary' => $query->summary($dates['from'], $dates['to']),
            'filters' => $request->only(['preset', 'from_date', 'to_date', 'supplier', 'product', 'group_by']),
            'suppliers' => fn () => Supplier::pluck('name', 'id'),
            'presets' => DatePreset::presets(),
        ]);
    }
}
