<?php

namespace App\Reports;

use App\Models\User;
use App\Queries\Reports\PosSessionReportQuery;
use App\Services\Utils\DatePreset;
use Illuminate\Http\Request;

class PosSessionReport implements Report
{
    public function __construct(
        private PosSessionReportQuery $query,
        private DatePreset $datePreset,
    ) {}

    public function page(): string
    {
        return 'Reports/PosSession';
    }

    public function props(Request $request): array
    {
        $dates = $this->datePreset->fromRequest($request);

        return [
            'data' => $this->query->get($dates['from'], $dates['to']),
            'summary' => $this->query->summary($dates['from'], $dates['to']),
            'filters' => $request->only(['preset', 'from_date', 'to_date', 'operator']),
            'operators' => fn () => User::pluck('name', 'id'),
            'presets' => DatePreset::presets(),
        ];
    }
}
