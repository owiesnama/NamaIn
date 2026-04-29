<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\TreasuryAccount;
use App\Queries\Reports\TreasuryReconciliationQuery;
use App\Services\ReportDateResolver;
use Illuminate\Http\Request;

class TreasuryReconciliationController extends Controller
{
    public function index(Request $request, TreasuryReconciliationQuery $query, ReportDateResolver $dateResolver)
    {
        abort_unless(auth()->user()->hasPermission('reports.view'), 403);

        $dates = $dateResolver->resolve($request);
        $accountId = $request->input('treasury_account') ? (int) $request->input('treasury_account') : null;

        return inertia('Reports/TreasuryReconciliation', [
            'data' => $query->getData($dates['from'], $dates['to'], $accountId),
            'summary' => $query->getSummary($dates['from'], $dates['to'], $accountId),
            'filters' => $request->only(['preset', 'from_date', 'to_date', 'treasury_account']),
            'accounts' => fn () => TreasuryAccount::active()->pluck('name', 'id'),
            'presets' => ReportDateResolver::presets(),
        ]);
    }
}
