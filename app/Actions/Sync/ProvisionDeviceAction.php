<?php

namespace App\Actions\Sync;

use App\Enums\DeviceStatus;
use App\Exceptions\Sync\ProvisionException;
use App\Models\ChangeLog;
use App\Models\Device;
use Illuminate\Support\Facades\DB;

/**
 * Exchanges a one-time pairing code for the device's Sanctum token (Design 02
 * §1.3). The pairing code is the sole credential: the endpoint is
 * unauthenticated, the stored hash is single-use, and success flips the device
 * to `active`, clears the hash, and mints the token with the four sync
 * abilities.
 */
class ProvisionDeviceAction
{
    /**
     * @return array{device: Device, token: string}
     */
    public function handle(string $pairingCode, ?string $deviceName = null): array
    {
        $device = $this->lookup($pairingCode);

        $this->ensureProvisionable($device);

        return DB::transaction(function () use ($device, $deviceName) {
            ChangeLog::lockTenant($device->tenant_id);

            $device->update([
                'name' => $deviceName ?? $device->name,
                'status' => DeviceStatus::Active,
                'pairing_code_hash' => null,
                'pairing_expires_at' => null,
                'provisioned_at' => now(),
            ]);

            $token = $device->createToken('sync', Device::TOKEN_ABILITIES)->plainTextToken;

            return ['device' => $device, 'token' => $token];
        });
    }

    private function lookup(string $pairingCode): Device
    {
        $hash = hash('sha256', $pairingCode);

        $device = Device::where('pairing_code_hash', $hash)->first();

        if (! $device || ! hash_equals($device->pairing_code_hash, $hash)) {
            throw ProvisionException::invalidPairingCode();
        }

        return $device;
    }

    private function ensureProvisionable(Device $device): void
    {
        if (! $device->tenant->isOfflineEnabled()) {
            throw ProvisionException::offlineDisabled();
        }

        if ($device->isRevoked()) {
            throw ProvisionException::invalidPairingCode();
        }

        if ($device->isActive() || $device->provisioned_at !== null) {
            throw ProvisionException::alreadyProvisioned();
        }

        if ($device->pairing_expires_at === null || $device->pairing_expires_at->isPast()) {
            throw ProvisionException::pairingExpired();
        }
    }
}
