<?php

namespace App\Reports;

use App\Queries\Reports\ProfitAndLossQuery;
use App\Services\Utils\DatePreset;
use Illuminate\Http\Request;

class ProfitAndLossReport implements Report
{
    public function __construct(
        private ProfitAndLossQuery $query,
        private DatePreset $datePreset,
    ) {}

    public function page(): string
    {
        return 'Reports/ProfitAndLoss';
    }

    public function props(Request $request): array
    {
        $dates = $this->datePreset->fromRequest($request);

        return [
            'data' => $this->query->get($dates['from'], $dates['to']),
            'summary' => $this->query->summary($dates['from'], $dates['to']),
            'filters' => $request->only(['preset', 'from_date', 'to_date']),
            'presets' => DatePreset::presets(),
        ];
    }
}
