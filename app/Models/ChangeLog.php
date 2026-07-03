<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The per-tenant change ledger. Not a BaseModel — it must never record itself.
 *
 * `record()` appends an identity-only entry, allocating the per-tenant `seq`
 * from a locked counter row so appends serialize per tenant (the cursor can
 * never skip). `lockTenant()` acquires that same counter lock at the top of a
 * syncable-write transaction, before any business-row lock, giving a global
 * lock order of tenant-counter → business rows (deadlock-free, §4.3).
 */
class ChangeLog extends Model
{
    protected $table = 'change_log';

    public $timestamps = false;

    protected $guarded = [];

    /** Memoized once the sync tables exist (they never disappear afterwards). */
    private static bool $tablesReady = false;

    /**
     * Acquire the tenant's change-counter lock first, before any business row.
     */
    public static function lockTenant(?int $tenantId): void
    {
        if ($tenantId === null || ! static::tablesReady()) {
            return;
        }

        static::ensureCounter($tenantId);

        DB::table('tenant_sync_state')->where('tenant_id', $tenantId)->lockForUpdate()->first();
    }

    /**
     * Append an identity-only change entry inside the current transaction.
     */
    public static function record(string $table, ?string $publicId, string $operation, ?int $tenantId): void
    {
        if ($tenantId === null || $publicId === null || ! static::tablesReady()) {
            return;
        }

        DB::table('change_log')->insert([
            'tenant_id' => $tenantId,
            'seq' => static::allocateSeq($tenantId),
            'table_name' => $table,
            'public_id' => $publicId,
            'operation' => $operation,
            'source_device_id' => null,
            'actor_user_id' => auth()->id(),
            'changed_at' => now(),
        ]);
    }

    private static function allocateSeq(int $tenantId): int
    {
        static::ensureCounter($tenantId);

        $seq = (int) DB::table('tenant_sync_state')
            ->where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->value('next_seq');

        DB::table('tenant_sync_state')->where('tenant_id', $tenantId)->update([
            'next_seq' => $seq + 1,
            'updated_at' => now(),
        ]);

        return $seq;
    }

    private static function ensureCounter(int $tenantId): void
    {
        DB::table('tenant_sync_state')->insertOrIgnore([
            'tenant_id' => $tenantId,
            'next_seq' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private static function tablesReady(): bool
    {
        if (static::$tablesReady) {
            return true;
        }

        if (Schema::hasTable('change_log') && Schema::hasTable('tenant_sync_state')) {
            static::$tablesReady = true;
        }

        return static::$tablesReady;
    }
}
