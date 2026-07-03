<?php

namespace App\Reports;

use App\Models\Supplier;
use App\Queries\Reports\PurchaseReportQuery;
use App\Services\Utils\DatePreset;
use Illuminate\Http\Request;

class PurchaseReport implements Report
{
    public function __construct(
        private PurchaseReportQuery $query,
        private DatePreset $datePreset,
    ) {}

    public function page(): string
    {
        return 'Reports/Purchase';
    }

    public function props(Request $request): array
    {
        $dates = $this->datePreset->fromRequest($request);

        return [
            'data' => $this->query->get($dates['from'], $dates['to'], $request->input('group_by', 'day')),
            'summary' => $this->query->summary($dates['from'], $dates['to']),
            'filters' => $request->only(['preset', 'from_date', 'to_date', 'supplier', 'product', 'group_by']),
            'suppliers' => fn () => Supplier::pluck('name', 'id'),
            'presets' => DatePreset::presets(),
        ];
    }
}
