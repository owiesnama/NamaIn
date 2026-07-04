<?php

namespace App\Actions\Sync;

use App\Enums\DeviceStatus;
use App\Enums\StorageType;
use App\Enums\TreasuryAccountType;
use App\Models\ChangeLog;
use App\Models\Device;
use App\Models\Register;
use App\Models\Storage;
use App\Models\TreasuryAccount;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Web-side device enrollment (Design 02 §1.3): a `devices.manage` user creates
 * a pending Device with its own Register (next per-tenant `R{n}` code) and cash
 * drawer, and receives the one-time pairing code. Only the sha256 hash of the
 * code is stored; the plaintext is shown once and expires after 15 minutes.
 */
class EnrollDeviceAction
{
    public const PAIRING_CODE_TTL_MINUTES = 15;

    /** Unambiguous alphabet (no 0/O/1/I/L) — the code is typed by hand on the device. */
    private const PAIRING_ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    /**
     * @return array{device: Device, pairing_code: string}
     */
    public function handle(Storage $storage, string $name): array
    {
        if ($storage->type !== StorageType::SALE_POINT) {
            throw new InvalidArgumentException('A device register must bind to a sale-point storage.');
        }

        return DB::transaction(function () use ($storage, $name) {
            ChangeLog::lockTenant($storage->tenant_id);

            $register = Register::create([
                'tenant_id' => $storage->tenant_id,
                'storage_id' => $storage->id,
                'code' => $this->nextRegisterCode($storage->tenant_id),
                'label' => $name,
            ]);

            TreasuryAccount::create([
                'tenant_id' => $storage->tenant_id,
                'name' => __('Cash Drawer :code', ['code' => $register->code]),
                'type' => TreasuryAccountType::Cash,
                'sale_point_id' => $storage->id,
                'register_id' => $register->id,
                'opening_balance' => 0,
                'currency' => 'SDG',
            ]);

            $pairingCode = $this->generatePairingCode();

            $device = Device::create([
                'tenant_id' => $storage->tenant_id,
                'register_id' => $register->id,
                'name' => $name,
                'status' => DeviceStatus::Pending,
                'pairing_code_hash' => hash('sha256', $pairingCode),
                'pairing_expires_at' => now()->addMinutes(self::PAIRING_CODE_TTL_MINUTES),
            ]);

            return ['device' => $device, 'pairing_code' => $pairingCode];
        });
    }

    /**
     * Next system-assigned register code (`R1`, `R2`, …). `R0` is the reserved
     * cloud register; ensuring it exists first guarantees devices never get it.
     * Race-safe: the tenant change-counter lock serializes enrollments.
     */
    private function nextRegisterCode(int $tenantId): string
    {
        Register::cloudRegisterFor($tenantId);

        $highest = Register::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->pluck('code')
            ->map(fn (string $code) => (int) ltrim($code, 'R'))
            ->max();

        return 'R'.($highest + 1);
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
