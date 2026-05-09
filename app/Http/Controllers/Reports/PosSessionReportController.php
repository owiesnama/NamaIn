<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Queries\Reports\PosSessionReportQuery;
use App\Services\Utils\DatePreset;
use Illuminate\Http\Request;

class PosSessionReportController extends Controller
{
    public function index(Request $request, PosSessionReportQuery $query, DatePreset $dateResolver)
    {
        abort_unless(auth()->user()->hasPermission('reports.view'), 403);

        $dates = $dateResolver->fromRequest($request);

        return inertia('Reports/PosSession', [
            'data' => $query->get($dates['from'], $dates['to']),
            'summary' => $query->summary($dates['from'], $dates['to']),
            'filters' => $request->only(['preset', 'from_date', 'to_date', 'operator']),
            'operators' => fn () => User::pluck('name', 'id'),
            'presets' => DatePreset::presets(),
        ]);
    }
}
