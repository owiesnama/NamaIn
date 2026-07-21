<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** @var array<int, string> */
    private array $tables = [
        'invoices', 'transactions', 'transaction_receipts', 'payments',
        'customers', 'customer_advances', 'suppliers', 'products', 'units',
        'categories', 'stocks', 'stock_movements', 'stock_transfers',
        'stock_transfer_lines', 'adjustments', 'storages', 'pos_sessions',
        'treasury_accounts', 'treasury_movements', 'treasury_transfers',
        'cheques', 'banks', 'expenses', 'recurring_expenses', 'quotes',
        'quote_items', 'preferences',
        'users', 'roles', 'permissions', 'tenants',
    ];

    /**
     * Backfill `public_id` for every pre-existing row, chunked and driver-portable.
     *
     * public_id is globally unique per table (not tenant-scoped): a device-minted
     * ULID must never collide with a cloud-minted one. Idempotent — only touches
     * rows where public_id is still null.
     */
    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasColumn($table, 'public_id')) {
                continue;
            }

            do {
                $ids = DB::table($table)->whereNull('public_id')->limit(2000)->pluck('id');

                foreach ($ids as $id) {
                    DB::table($table)->where('id', $id)->update([
                        'public_id' => strtolower((string) Str::ulid()),
                    ]);
                }
            } while ($ids->isNotEmpty());
        }
    }

    public function down(): void
    {
        // No-op: the column drop in the sibling migration removes the data.
    }
};
