<?php

namespace App\Services\Sync;

use App\Models\ChangeLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The idempotency gate (Design 01 §6.2). Within one transaction:
 *
 *   1. SELECT ... FOR UPDATE on (tenant_id, idempotency_key).
 *   2. If a row exists, return the stored result verbatim — zero writes, so no
 *      duplicate change-log entries and no duplicate business rows.
 *   3. Otherwise run the mutation and record its result.
 *
 * The unique (tenant_id, idempotency_key) index is the ultimate arbiter: a
 * concurrent duplicate loses the insert and is retried into the replay branch.
 */
class IdempotentMutation
{
    /**
     * @param  Closure():mixed  $mutation  Produces a Model or an array result.
     */
    public function run(
        int $tenantId,
        string $idempotencyKey,
        string $mutationType,
        Closure $mutation,
        ?int $deviceId = null,
    ): IdempotencyOutcome {
        return DB::transaction(function () use ($tenantId, $idempotencyKey, $mutationType, $mutation, $deviceId) {
            // Lock the tenant change counter first (lock-order discipline, §4.3).
            ChangeLog::lockTenant($tenantId);

            $existing = DB::table('sync_idempotency')
                ->where('tenant_id', $tenantId)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return new IdempotencyOutcome(
                    replayed: true,
                    status: $existing->status,
                    resultPublicId: $existing->result_public_id,
                    result: $existing->result ? json_decode($existing->result, true) : null,
                );
            }

            [$resultPublicId, $result] = $this->normalize($mutation());

            DB::table('sync_idempotency')->insert([
                'tenant_id' => $tenantId,
                'device_id' => $deviceId,
                'idempotency_key' => $idempotencyKey,
                'mutation_type' => $mutationType,
                'result_public_id' => $resultPublicId,
                'status' => 'applied',
                'result' => $result !== null ? json_encode($result) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return new IdempotencyOutcome(false, 'applied', $resultPublicId, $result);
        });
    }

    /**
     * @return array{0: ?string, 1: array<string, mixed>|null}
     */
    private function normalize(mixed $value): array
    {
        if ($value instanceof Model) {
            $publicId = $value->public_id ?? null;

            return [$publicId, ['public_id' => $publicId]];
        }

        if (is_array($value)) {
            return [$value['public_id'] ?? null, $value];
        }

        return [null, null];
    }
}
