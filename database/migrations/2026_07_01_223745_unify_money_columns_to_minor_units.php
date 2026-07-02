<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every money column that stored major units. Existing values are
     * multiplied by 100 and the column becomes a bigint holding minor
     * units, matching the treasury and POS tables.
     *
     * @var array<string, array<string, array{nullable: bool, default: int|null}>>
     */
    private const MONEY_COLUMNS = [
        'invoices' => [
            'total' => ['nullable' => false, 'default' => null],
            'paid_amount' => ['nullable' => false, 'default' => 0],
            'discount' => ['nullable' => false, 'default' => 0],
        ],
        'payments' => [
            'amount' => ['nullable' => false, 'default' => null],
        ],
        'expenses' => [
            'amount' => ['nullable' => false, 'default' => null],
        ],
        'recurring_expenses' => [
            'amount' => ['nullable' => false, 'default' => null],
        ],
        'cheques' => [
            'amount' => ['nullable' => false, 'default' => null],
            'cleared_amount' => ['nullable' => false, 'default' => 0],
        ],
        'customer_advances' => [
            'amount' => ['nullable' => false, 'default' => null],
            'settled_amount' => ['nullable' => false, 'default' => 0],
        ],
        'quotes' => [
            'discount' => ['nullable' => false, 'default' => 0],
        ],
        'quote_items' => [
            'unit_price' => ['nullable' => false, 'default' => null],
        ],
        'transactions' => [
            'price' => ['nullable' => false, 'default' => null],
            'unit_cost' => ['nullable' => true, 'default' => null],
        ],
        'products' => [
            'price' => ['nullable' => false, 'default' => 0],
            'cost' => ['nullable' => false, 'default' => 0],
            'average_cost' => ['nullable' => false, 'default' => 0],
        ],
        'customers' => [
            'opening_debit' => ['nullable' => false, 'default' => 0],
            'opening_credit' => ['nullable' => false, 'default' => 0],
            'credit_limit' => ['nullable' => false, 'default' => 0],
        ],
        'suppliers' => [
            'opening_debit' => ['nullable' => false, 'default' => 0],
            'opening_credit' => ['nullable' => false, 'default' => 0],
        ],
        'categories' => [
            'budget_limit' => ['nullable' => true, 'default' => null],
        ],
    ];

    public function up(): void
    {
        foreach (self::MONEY_COLUMNS as $table => $columns) {
            foreach ($columns as $column => $shape) {
                $this->convertToMinorUnits($table, $column, $shape);
            }
        }
    }

    public function down(): void
    {
        foreach (self::MONEY_COLUMNS as $table => $columns) {
            foreach ($columns as $column => $shape) {
                $this->revertToMajorUnits($table, $column, $shape);
            }
        }
    }

    /**
     * @param  array{nullable: bool, default: int|null}  $shape
     */
    private function convertToMinorUnits(string $table, string $column, array $shape): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} DROP DEFAULT");
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE BIGINT USING ROUND({$column} * 100)::bigint");

            if ($shape['default'] !== null) {
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET DEFAULT {$shape['default']}");
            }

            return;
        }

        DB::table($table)->update([$column => DB::raw("ROUND({$column} * 100)")]);

        Schema::table($table, function (Blueprint $blueprint) use ($column, $shape) {
            $definition = $blueprint->bigInteger($column)->nullable($shape['nullable']);

            if ($shape['default'] !== null) {
                $definition->default($shape['default']);
            }

            $definition->change();
        });
    }

    /**
     * @param  array{nullable: bool, default: int|null}  $shape
     */
    private function revertToMajorUnits(string $table, string $column, array $shape): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} DROP DEFAULT");
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE NUMERIC(15, 2) USING ({$column} / 100.0)");

            if ($shape['default'] !== null) {
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET DEFAULT {$shape['default']}");
            }

            return;
        }

        DB::table($table)->update([$column => DB::raw("{$column} / 100.0")]);

        Schema::table($table, function (Blueprint $blueprint) use ($column, $shape) {
            $definition = $blueprint->decimal($column, 15, 2)->nullable($shape['nullable']);

            if ($shape['default'] !== null) {
                $definition->default($shape['default']);
            }

            $definition->change();
        });
    }
};
