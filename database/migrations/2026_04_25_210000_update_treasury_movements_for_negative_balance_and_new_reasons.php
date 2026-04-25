<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ALL_REASONS = [
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
        'customer_advance_repaid',
        'cheque_received',
        'cheque_cleared',
    ];

    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            // 1. Drop the existing reason check constraint
            DB::statement('ALTER TABLE treasury_movements DROP CONSTRAINT IF EXISTS treasury_movements_reason_check');

            // 2. Re-add it with all current values
            $values = implode(', ', array_map(fn ($v) => "'$v'", self::ALL_REASONS));
            DB::statement("ALTER TABLE treasury_movements ADD CONSTRAINT treasury_movements_reason_check CHECK (reason IN ($values))");

            // 3. Allow balance_after to be negative (was unsigned / non-negative)
            DB::statement('ALTER TABLE treasury_movements ALTER COLUMN balance_after TYPE bigint');
            DB::statement('ALTER TABLE treasury_movements DROP CONSTRAINT IF EXISTS treasury_movements_balance_after_check');
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            $enumList = implode(', ', array_map(fn ($v) => "'$v'", self::ALL_REASONS));
            DB::statement("ALTER TABLE treasury_movements MODIFY COLUMN reason ENUM($enumList) NOT NULL");
            DB::statement('ALTER TABLE treasury_movements MODIFY COLUMN balance_after BIGINT NOT NULL');
        }
        // SQLite: no action needed — TEXT column already accepts any string,
        // and INTEGER columns already support negatives.
    }

    public function down(): void
    {
        // Non-trivial to reverse; left intentionally empty.
    }
};
