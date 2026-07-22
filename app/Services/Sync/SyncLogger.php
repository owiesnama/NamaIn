<?php

namespace App\Services\Sync;

use App\Models\Device;
use Illuminate\Support\Facades\DB;

/**
 * Writes the sync audit trail (Design 02 §8.1). Endpoints enrich the row with
 * their own fields (cursor window for pull, mutation counts for push) via the
 * request attribute bag; the middleware supplies endpoint + latency.
 */
class SyncLogger
{
    /**
     * @param  array<string, mixed>  $fields
     */
    public function record(Device $device, string $endpoint, int $durationMs, array $fields = []): void
    {
        DB::table('sync_logs')->insert([
            'tenant_id' => $device->tenant_id,
            'device_id' => $device->id,
            'endpoint' => $endpoint,
            'cursor_from' => $fields['cursor_from'] ?? null,
            'cursor_to' => $fields['cursor_to'] ?? null,
            'mutation_count' => $fields['mutation_count'] ?? 0,
            'applied_count' => $fields['applied_count'] ?? 0,
            'rejected_count' => $fields['rejected_count'] ?? 0,
            'duration_ms' => $durationMs,
            'client_pushed_at' => $fields['client_pushed_at'] ?? null,
            'created_at' => now(),
        ]);
    }
}
