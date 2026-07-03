<?php

namespace App\Reports;

use App\Models\TreasuryAccount;
use App\Queries\Reports\TreasuryReconciliationQuery;
use App\Services\Utils\DatePreset;
use Illuminate\Http\Request;

class TreasuryReconciliationReport implements Report
{
    public function __construct(
        private TreasuryReconciliationQuery $query,
        private DatePreset $datePreset,
    ) {}

    public function page(): string
    {
        return 'Reports/TreasuryReconciliation';
    }

    public function props(Request $request): array
    {
        $dates = $this->datePreset->fromRequest($request);

        $accountId = $request->input('treasury_account') ? (int) $request->input('treasury_account') : null;

        return [
            'data' => $this->query->get($dates['from'], $dates['to'], $accountId),
            'summary' => $this->query->summary($dates['from'], $dates['to'], $accountId),
            'filters' => $request->only(['preset', 'from_date', 'to_date', 'treasury_account']),
            'accounts' => fn () => TreasuryAccount::active()->pluck('name', 'id'),
            'presets' => DatePreset::presets(),
        ];
    }
}
