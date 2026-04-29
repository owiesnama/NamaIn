<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\ReportDateResolver;

class ReportsIndexController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->hasPermission('reports.view'), 403);

        return inertia('Reports/Index', [
            'presets' => ReportDateResolver::presets(),
        ]);
    }
}
