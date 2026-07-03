<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Reports\ReportRegistry;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __invoke(Request $request, ReportRegistry $reports, string $report)
    {
        $resolved = $reports->resolve($report);

        abort_unless($resolved !== null, 404);

        return inertia($resolved->page(), $resolved->props($request));
    }
}
