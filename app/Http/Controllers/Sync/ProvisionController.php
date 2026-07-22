<?php

namespace App\Http\Controllers\Sync;

use App\Actions\Sync\ProvisionDeviceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sync\ProvisionDeviceRequest;
use App\Models\TreasuryAccount;
use App\Services\Sync\SyncProtocol;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/sync/v1/provision — unauthenticated; the pairing code is the
 * credential. Response shape per Design 02 §1.3.
 */
class ProvisionController extends Controller
{
    public function __invoke(ProvisionDeviceRequest $request, ProvisionDeviceAction $action): JsonResponse
    {
        $provisioned = $action->handle(
            $request->string('pairing_code')->value(),
            $request->input('device_name'),
        );

        $device = $provisioned['device'];

        // The request carries no tenant; bind it from the device so the
        // tenant-scoped identity lookups below resolve.
        app()->instance('currentTenant', $device->tenant);

        $register = $device->register;
        $storage = $register->storage;
        $drawer = TreasuryAccount::where('register_id', $register->id)->first();

        return response()->json([
            'token' => $provisioned['token'],
            'device' => [
                'public_id' => $device->public_id,
                'name' => $device->name,
                'status' => $device->status->value,
            ],
            'register' => [
                'public_id' => $register->public_id,
                'code' => $register->code,
                'label' => $register->label,
            ],
            'storage' => [
                'public_id' => $storage->public_id,
                'name' => $storage->name,
                'type' => $storage->type->value,
            ],
            'tenant' => [
                'public_id' => $device->tenant->public_id,
                'name' => $device->tenant->name,
            ],
            'drawer' => [
                'public_id' => $drawer->public_id,
                'type' => $drawer->type->value,
            ],
            'cursor' => 0,
            'protocol' => SyncProtocol::VERSION,
        ], 201);
    }
}
