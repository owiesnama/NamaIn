<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\TreasuryAccount;
use App\Queries\Reports\TreasuryReconciliationQuery;
use App\Services\Utils\DatePreset;
use Illuminate\Http\Request;

class TreasuryReconciliationController extends Controller
{
    public function index(Request $request, TreasuryReconciliationQuery $query, DatePreset $datePreset)
    {
        $dates = $datePreset->fromRequest($request);

        $accountId = $request->input('treasury_account') ? (int) $request->input('treasury_account') : null;

        return inertia('Reports/TreasuryReconciliation', [
            'data' => $query->get($dates['from'], $dates['to'], $accountId),
            'summary' => $query->summary($dates['from'], $dates['to'], $accountId),
            'filters' => $request->only(['preset', 'from_date', 'to_date', 'treasury_account']),
            'accounts' => fn () => TreasuryAccount::active()->pluck('name', 'id'),
            'presets' => DatePreset::presets(),
        ]);
    }
}
