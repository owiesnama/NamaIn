<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Queries\Reports\ProfitAndLossQuery;
use App\Services\Utils\DatePreset;
use Illuminate\Http\Request;

class ProfitAndLossController extends Controller
{
    public function index(Request $request, ProfitAndLossQuery $query, DatePreset $dateResolver)
    {
        $dates = $dateResolver->fromRequest($request);

        return inertia('Reports/ProfitAndLoss', [
            'data' => $query->get($dates['from'], $dates['to']),
            'summary' => $query->summary($dates['from'], $dates['to']),
            'filters' => $request->only(['preset', 'from_date', 'to_date']),
            'presets' => DatePreset::presets(),
        ]);
    }
}
