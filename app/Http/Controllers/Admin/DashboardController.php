<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return inertia('Admin/Dashboard', [
            'stats' => [
                'totalTenants' => Tenant::count(),
                'activeTenants' => Tenant::where('is_active', true)->count(),
                'inactiveTenants' => Tenant::where('is_active', false)->count(),
                'totalUsers' => User::count(),
            ],
            'mostActiveTenants' => $this->mostActiveTenants(),
            'registrationTrends' => $this->registrationTrends(),
        ]);
    }

    /** @return Collection<int, object> */
    private function mostActiveTenants(): Collection
    {
        return Tenant::query()
            ->select('tenants.id', 'tenants.name', 'tenants.slug')
            ->where('is_active', true)
            ->addSelect([
                'invoices_count' => Invoice::withoutGlobalScopes()
                    ->selectRaw('count(*)')
                    ->whereColumn('tenant_id', 'tenants.id'),
                'users_count' => DB::table('tenant_user')
                    ->selectRaw('count(*)')
                    ->whereColumn('tenant_id', 'tenants.id')
                    ->where('is_active', true),
            ])
            ->orderByDesc(DB::raw('(SELECT count(*) FROM invoices WHERE tenant_id = tenants.id) + (SELECT count(*) FROM tenant_user WHERE tenant_id = tenants.id AND is_active = true)'))
            ->limit(10)
            ->get();
    }

    /** @return array{labels: array<string>, tenants: array<int>, users: array<int>} */
    private function registrationTrends(): array
    {
        $days = collect(range(29, 0))->map(fn ($i) => now()->subDays($i)->format('Y-m-d'));

        $tenantsByDay = Tenant::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, count(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        $usersByDay = User::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, count(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        return [
            'labels' => $days->map(fn ($d) => Carbon::parse($d)->format('M d'))->values()->all(),
            'tenants' => $days->map(fn ($d) => (int) ($tenantsByDay[$d] ?? 0))->values()->all(),
            'users' => $days->map(fn ($d) => (int) ($usersByDay[$d] ?? 0))->values()->all(),
        ];
    }
}
