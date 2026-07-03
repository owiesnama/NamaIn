<?php

namespace App\Reports;

use App\Models\Category;
use App\Queries\Reports\ExpenseSummaryQuery;
use App\Services\Utils\DatePreset;
use Illuminate\Http\Request;

class ExpenseSummaryReport implements Report
{
    public function __construct(
        private ExpenseSummaryQuery $query,
        private DatePreset $datePreset,
    ) {}

    public function page(): string
    {
        return 'Reports/ExpenseSummary';
    }

    public function props(Request $request): array
    {
        $dates = $this->datePreset->fromRequest($request);

        return [
            'data' => $this->query->get($dates['from'], $dates['to']),
            'summary' => $this->query->summary($dates['from'], $dates['to']),
            'filters' => $request->only(['preset', 'from_date', 'to_date', 'category']),
            'categories' => fn () => Category::pluck('name', 'id'),
            'presets' => DatePreset::presets(),
        ];
    }
}
