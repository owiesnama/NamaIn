<?php

namespace App\Reports;

use App\Models\Customer;
use App\Queries\Reports\SalesReportQuery;
use App\Services\Utils\DatePreset;
use Illuminate\Http\Request;

class SalesReport implements Report
{
    public function __construct(
        private SalesReportQuery $query,
        private DatePreset $datePreset,
    ) {}

    public function page(): string
    {
        return 'Reports/Sales';
    }

    public function props(Request $request): array
    {
        $dates = $this->datePreset->fromRequest($request);

        $groupBy = $request->input('group_by', 'day');

        return [
            'data' => $this->query->get($dates['from'], $dates['to'], $groupBy),
            'summary' => $this->query->summary($dates['from'], $dates['to']),
            'filters' => $request->only(['preset', 'from_date', 'to_date', 'customer', 'product', 'payment_method', 'channel', 'group_by']),
            'customers' => fn () => Customer::pluck('name', 'id'),
            'presets' => DatePreset::presets(),
        ];
    }
}
