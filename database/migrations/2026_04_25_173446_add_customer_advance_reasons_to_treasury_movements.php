<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite does not support ALTER COLUMN for enums; we use string instead.
        // For MySQL/MariaDB, we alter the enum to include the new values.
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            Schema::getConnection()->statement("
                ALTER TABLE treasury_movements MODIFY COLUMN reason ENUM(
                    'payment_received',
                    'payment_refunded',
                    'expense_paid',
                    'transfer_in',
                    'transfer_out',
                    'pos_opening_float',
                    'pos_closing_float',
                    'cheque_deposited',
                    'cheque_bounced',
                    'manual_adjustment',
                    'customer_advance_given',
                    'customer_advance_repaid'
                ) NOT NULL
            ");
        } elseif ($driver === 'sqlite') {
            // SQLite: the enum is stored as TEXT with a CHECK constraint.
            // We cannot drop/recreate the CHECK in-place on SQLite, so we
            // recreate the column as a plain string (SQLite ignores CHECK add).
            Schema::table('treasury_movements', function (Blueprint $table) {
                $table->string('reason_new')->nullable()->after('movable_id');
            });

            DB::statement('UPDATE treasury_movements SET reason_new = reason');

            Schema::table('treasury_movements', function (Blueprint $table) {
                $table->dropColumn('reason');
            });

            Schema::table('treasury_movements', function (Blueprint $table) {
                $table->renameColumn('reason_new', 'reason');
            });
        }
    }

    public function down(): void
    {
        // Reverse is non-trivial; simply leave as-is for down migrations.
    }
};
