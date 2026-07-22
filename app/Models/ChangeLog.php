<?php

namespace App\Models;

use App\Services\Sync\OfflineSync;
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

    /**
     * The FR-1 syncable set (Design 01): the only tables the change log tracks
     * and the sync API will ever serve. Tables outside it — even ones carrying a
     * `public_id`, like registers and devices — are never recorded.
     *
     * @var array<int, string>
     */
    public const SYNCABLE_TABLES = [
        'invoices', 'transactions', 'transaction_receipts', 'payments',
        'customers', 'customer_advances', 'suppliers', 'products', 'units',
        'categories', 'stocks', 'stock_movements', 'stock_transfers',
        'stock_transfer_lines', 'adjustments', 'storages', 'pos_sessions',
        'treasury_accounts', 'treasury_movements', 'treasury_transfers',
        'cheques', 'banks', 'expenses', 'recurring_expenses', 'quotes',
        'quote_items', 'preferences',
    ];

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

        if (! OfflineSync::enabledFor($tenantId)) {
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

        if (! in_array($table, self::SYNCABLE_TABLES, true)) {
            return;
        }

        if (! OfflineSync::enabledFor($tenantId)) {
            return;
        }

        DB::table('change_log')->insert([
            'tenant_id' => $tenantId,
            'seq' => static::allocateSeq($tenantId),
            'table_name' => $table,
            'public_id' => $publicId,
            'operation' => $operation,
            'source_device_id' => static::sourceDeviceId(),
            'actor_user_id' => auth()->id(),
            'changed_at' => now(),
        ]);
    }

    /**
     * The device that authored the current write, when one pushed it (bound by
     * BindDeviceTenant). Null for cloud-web writes — no device is in context.
     */
    private static function sourceDeviceId(): ?int
    {
        if (! app()->bound('currentDevice')) {
            return null;
        }

        return app('currentDevice')->id;
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
