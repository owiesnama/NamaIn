<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables that carry a sync `public_id`: the FR-1 syncable set plus the
     * identity tables (users, roles, permissions, tenants) referenced by
     * sync payloads.
     *
     * @var array<int, string>
     */
    public const TABLES = [
        'invoices', 'transactions', 'transaction_receipts', 'payments',
        'customers', 'customer_advances', 'suppliers', 'products', 'units',
        'categories', 'stocks', 'stock_movements', 'stock_transfers',
        'stock_transfer_lines', 'adjustments', 'storages', 'pos_sessions',
        'treasury_accounts', 'treasury_movements', 'treasury_transfers',
        'cheques', 'banks', 'expenses', 'recurring_expenses', 'quotes',
        'quote_items', 'preferences',
        'users', 'roles', 'permissions', 'tenants',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasColumn($table, 'public_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->char('public_id', 26)->nullable()->after('id');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'public_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('public_id');
            });
        }
    }
};
