<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
     * Now that every row is backfilled, lock the column down: non-null and
     * globally unique per table.
     */
    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->char('public_id', 26)->nullable(false)->change();
                $blueprint->unique('public_id', "{$table}_public_id_unique");
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropUnique("{$table}_public_id_unique");
                $blueprint->char('public_id', 26)->nullable()->change();
            });
        }
    }
};
