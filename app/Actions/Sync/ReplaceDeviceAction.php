<?php

namespace App\Actions\Sync;

use App\Enums\DeviceStatus;
use App\Models\ChangeLog;
use App\Models\Device;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The planned device swap (Design 04 §4.3, R10), distinct from revoke: drain the
 * outgoing device's outbox first, retire it, then provision a successor onto the
 * *same* register. Serial continuity is automatic — `register_serials` is keyed
 * by register, not device, so the successor resumes the sequence with no reset
 * and no gap (the snapshot seeds its local counter, Design 04 §9).
 */
class ReplaceDeviceAction
{
    /** Unambiguous alphabet (no 0/O/1/I/L) — the code is typed by hand. */
    private const PAIRING_ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    /**
     * @return array{device: Device, pairing_code: string}
     */
    public function handle(Device $device, ?string $name = null): array
    {
        if (($device->pending_count ?? 0) > 0) {
            throw new RuntimeException('The device outbox must drain before replacement.');
        }

        return DB::transaction(function () use ($device, $name): array {
            ChangeLog::lockTenant($device->tenant_id);

            $device->update([
                'status' => DeviceStatus::Revoked,
                'revoked_at' => now(),
            ]);
            $device->tokens->each->delete();

            $pairingCode = $this->generatePairingCode();

            $successor = Device::create([
                'tenant_id' => $device->tenant_id,
                'register_id' => $device->register_id, // same register → serial sequence continues
                'name' => $name ?? $device->name,
                'status' => DeviceStatus::Pending,
                'pairing_code_hash' => hash('sha256', $pairingCode),
                'pairing_expires_at' => now()->addMinutes(EnrollDeviceAction::PAIRING_CODE_TTL_MINUTES),
            ]);

            return ['device' => $successor, 'pairing_code' => $pairingCode];
        });
    }

    private function generatePairingCode(): string
    {
        $block = function (): string {
            $characters = '';
            for ($i = 0; $i < 5; $i++) {
                $characters .= self::PAIRING_ALPHABET[random_int(0, strlen(self::PAIRING_ALPHABET) - 1)];
            }

            return $characters;
        };

        return $block().'-'.$block();
    }
}
