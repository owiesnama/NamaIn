<?php

namespace App\Http\Controllers\Sync;

use App\Actions\Sync\EnrollDeviceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sync\StoreDeviceRequest;
use App\Models\Storage;
use Illuminate\Http\RedirectResponse;

/**
 * Web-side device enrollment for `devices.manage` users (Design 02 §1.3).
 * Backend only for Phase 1 — the fleet management UI lands in Phase 3. The
 * one-time pairing code is flashed to the session and never stored in plain.
 */
class DevicesController extends Controller
{
    public function store(StoreDeviceRequest $request, EnrollDeviceAction $action): RedirectResponse
    {
        $storage = Storage::findOrFail($request->integer('storage_id'));

        $enrollment = $action->handle($storage, $request->string('name')->value());

        return back()->with([
            'pairing_code' => $enrollment['pairing_code'],
            'device' => $enrollment['device']->public_id,
            'message' => __('Device enrolled. Enter the pairing code on the device within :minutes minutes.', [
                'minutes' => EnrollDeviceAction::PAIRING_CODE_TTL_MINUTES,
            ]),
        ]);
    }
}
