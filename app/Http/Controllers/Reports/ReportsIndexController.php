<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Utils\DatePreset;

class ReportsIndexController extends Controller
{
    public function index()
    {
        return inertia('Reports/Index', [
            'presets' => DatePreset::presets(),
        ]);
    }
}
