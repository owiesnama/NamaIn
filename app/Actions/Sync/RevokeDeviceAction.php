<?php

namespace App\Actions\Sync;

use App\Enums\DeviceStatus;
use App\Models\ChangeLog;
use App\Models\Device;
use Illuminate\Support\Facades\DB;

/**
 * Revokes a lost/stolen device (Design 04 §4.2, R10). Immediate token kill: any
 * in-flight sync call then 401s (missing token) and a re-auth attempt 403s
 * (`device_revoked`, via EnsureDeviceActive) so the client wipes. The last
 * device-reported `pending_count` is snapshotted into `revoked_unsynced_count`
 * as an honest, labelled approximation of what may be lost.
 */
class RevokeDeviceAction
{
    public function handle(Device $device): Device
    {
        return DB::transaction(function () use ($device): Device {
            ChangeLog::lockTenant($device->tenant_id);

            $device->update([
                'status' => DeviceStatus::Revoked,
                'revoked_at' => now(),
                'revoked_unsynced_count' => $device->pending_count,
            ]);

            $device->tokens->each->delete();

            return $device;
        });
    }
}
