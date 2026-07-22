<?php

namespace App\Http\Controllers\Sync;

use App\Actions\Sync\EnrollDeviceAction;
use App\Actions\Sync\ReplaceDeviceAction;
use App\Actions\Sync\RevokeDeviceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sync\StoreDeviceRequest;
use App\Models\Device;
use App\Models\Storage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Response;

/**
 * Web-side device fleet management (Design 02 §1.3, Design 04 §4). Enrollment
 * for `devices.manage`; the read-only fleet dashboard for `devices.view`; revoke
 * and replace for `devices.manage`. The one-time pairing code is flashed and
 * never stored in plain.
 */
class DevicesController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Device::class);

        $devices = Device::query()
            ->where('tenant_id', currentTenant()->id)
            ->with('register')
            ->latest('id')
            ->get()
            ->map(fn (Device $device): array => $this->summarize($device));

        return inertia('Devices/Index', [
            'devices' => $devices,
            'storages' => Storage::query()->salePoints()->get(['id', 'name'])
                ->map(fn ($storage): array => ['id' => $storage->id, 'name' => $storage->name]),
        ]);
    }

    public function show(Device $device): Response
    {
        $this->authorize('view', $device);
        $this->ensureTenant($device);

        $logs = DB::table('sync_logs')
            ->where('device_id', $device->id)
            ->latest('id')
            ->limit(50)
            ->get(['endpoint', 'cursor_from', 'cursor_to', 'mutation_count', 'applied_count', 'rejected_count', 'duration_ms', 'created_at']);

        return inertia('Devices/Show', [
            'device' => $this->summarize($device->load('register')),
            'logs' => $logs,
        ]);
    }

    public function store(StoreDeviceRequest $request, EnrollDeviceAction $action): RedirectResponse
    {
        $storage = Storage::findOrFail($request->integer('storage_id'));

        $enrollment = $action->handle($storage, $request->string('name')->value());

        // Under the single `flash` session key: that is the only shape
        // HandleInertiaRequests shares with the page (flash.pairing_code).
        return back()->with('flash', [
            'pairing_code' => $enrollment['pairing_code'],
            'device' => $enrollment['device']->public_id,
            'message' => __('Device enrolled. Enter the pairing code on the device within :minutes minutes.', [
                'minutes' => EnrollDeviceAction::PAIRING_CODE_TTL_MINUTES,
            ]),
        ]);
    }

    public function revoke(Device $device, RevokeDeviceAction $action): RedirectResponse
    {
        $this->authorize('manage', Device::class);
        $this->ensureTenant($device);

        $action->handle($device);

        return back()->with('success', __('Device revoked. It will wipe on its next launch.'));
    }

    public function replace(Request $request, Device $device, ReplaceDeviceAction $action): RedirectResponse
    {
        $this->authorize('manage', Device::class);
        $this->ensureTenant($device);

        $result = $action->handle($device, $request->string('name')->value() ?: null);

        return back()->with('flash', [
            'pairing_code' => $result['pairing_code'],
            'device' => $result['device']->public_id,
            'message' => __('Replacement enrolled on the same register. Enter the pairing code within :minutes minutes.', [
                'minutes' => EnrollDeviceAction::PAIRING_CODE_TTL_MINUTES,
            ]),
        ]);
    }

    private function ensureTenant(Device $device): void
    {
        abort_unless($device->tenant_id === currentTenant()->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function summarize(Device $device): array
    {
        $health = $device->health();

        return [
            'id' => $device->id,
            'public_id' => $device->public_id,
            'name' => $device->name,
            'register' => $device->register?->code,
            'register_label' => $device->register?->label,
            'status' => $device->status->value,
            'health' => $health->value,
            'health_label' => $health->label(),
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            'last_push_at' => $device->last_push_at?->toIso8601String(),
            'last_pull_at' => $device->last_pull_at?->toIso8601String(),
            'pending_count' => $device->pending_count,
            'oldest_pending_at' => $device->oldest_pending_at?->toIso8601String(),
            'app_version' => $device->app_version,
            'clock_skew_seconds' => $device->clock_skew_seconds,
            'revoked_unsynced_count' => $device->revoked_unsynced_count,
        ];
    }
}
