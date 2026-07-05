<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Queries\PilotHealthQuery;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Super-admin pilot health page (Design 04 §6.2-6.3, R14): runs the SLO
 * queries for a selected tenant and date range on demand. Read-only; the
 * item-level detail lives in the tenant's `report-reconciliation` export.
 */
class PilotHealthController extends Controller
{
    public function index(Request $request, PilotHealthQuery $query): Response
    {
        $this->authorize('viewAny', Tenant::class);

        $tenantId = $request->integer('tenant') ?: null;
        $from = $request->filled('from_date') ? Carbon::parse($request->input('from_date'))->startOfDay() : now()->subDays(28)->startOfDay();
        $to = $request->filled('to_date') ? Carbon::parse($request->input('to_date'))->endOfDay() : now()->endOfDay();

        return inertia('Admin/PilotHealth/Index', [
            'tenants' => Tenant::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'tenant' => $tenantId,
                'from_date' => $from->toDateString(),
                'to_date' => $to->toDateString(),
            ],
            'slos' => $tenantId ? $query->get($tenantId, $from, $to) : null,
        ]);
    }
}
