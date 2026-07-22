<?php

namespace App\Http\Controllers\Sync;

use App\Enums\DeviceStatus;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\Sync\SyncProtocol;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /detach (Design 03 §7.2): a device revokes itself. It deletes the token
 * used for this request and flips its status to `revoked`, so the token dies
 * immediately and PRD-04's device lifecycle picks up the revocation. The client
 * then wipes its local store.
 */
class DetachController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user('sync');

        $device->currentAccessToken()?->delete();
        $device->forceFill(['status' => DeviceStatus::Revoked])->save();

        return response()->json([
            'status' => DeviceStatus::Revoked->value,
            'protocol' => SyncProtocol::VERSION,
        ]);
    }
}
