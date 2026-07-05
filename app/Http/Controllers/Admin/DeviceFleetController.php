<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\LogAdminAction;
use App\Enums\DeviceStatus;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\ReconciliationItem;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

/**
 * Super-admin device fleet view (Design 04 §4.4, R11). Read-only across tenants
 * — device/active/revoked counts, last sync, aggregate open reconciliation
 * items, per-device health — with the single mutating control being the
 * per-tenant offline feature flag (§6.5), audited via LogAdminAction.
 */
class DeviceFleetController extends Controller
{
    public function __construct(private LogAdminAction $logger) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Tenant::class);

        $tenants = Tenant::query()
            ->when(request('search'), fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Tenant $tenant): array => $this->tenantSummary($tenant));

        return inertia('Admin/DeviceFleet/Index', [
            'tenants' => $tenants,
            'filters' => request()->only('search'),
        ]);
    }

    public function show(Tenant $tenant): Response
    {
        $this->authorize('view', $tenant);

        $devices = Device::query()
            ->where('tenant_id', $tenant->id)
            ->with('register')
            ->latest('id')
            ->get()
            ->map(fn (Device $device): array => [
                'name' => $device->name,
                'register' => $device->register?->code,
                'status' => $device->status->value,
                'health' => $device->health()->value,
                'health_label' => $device->health()->label(),
                'last_seen_at' => $device->last_seen_at?->toIso8601String(),
                'pending_count' => $device->pending_count,
                'clock_skew_seconds' => $device->clock_skew_seconds,
            ]);

        return inertia('Admin/DeviceFleet/Show', [
            'tenant' => ['id' => $tenant->id, 'name' => $tenant->name, 'offline_enabled' => $tenant->isOfflineEnabled()],
            'devices' => $devices,
            'open_reconciliation_count' => $this->openReconciliationCount($tenant),
        ]);
    }

    public function toggleOffline(Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $tenant->isOfflineEnabled() ? $tenant->disableOffline() : $tenant->enableOffline();

        $this->logger->handle(
            request()->user('admin')->id,
            $tenant->isOfflineEnabled() ? 'tenant.offline_enabled' : 'tenant.offline_disabled',
            $tenant,
        );

        return back()->with('success', __('Offline mode updated.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function tenantSummary(Tenant $tenant): array
    {
        $devices = Device::query()->where('tenant_id', $tenant->id)->get();

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'offline_enabled' => $tenant->isOfflineEnabled(),
            'device_count' => $devices->count(),
            'active_count' => $devices->where('status', DeviceStatus::Active)->count(),
            'revoked_count' => $devices->where('status', DeviceStatus::Revoked)->count(),
            'last_seen_at' => $devices->max('last_seen_at')?->toIso8601String(),
            'open_reconciliation_count' => $this->openReconciliationCount($tenant),
        ];
    }

    private function openReconciliationCount(Tenant $tenant): int
    {
        return ReconciliationItem::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('status', ReconciliationItem::STATUS_OPEN)
            ->count();
    }
}
