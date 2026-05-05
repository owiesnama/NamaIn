<?php

use App\Models\Customer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('average_cost')->default(0)->after('price');
        });

        $customerClass = addslashes(Customer::class);

        // Backfill from delivered purchase transactions
        DB::statement("
            UPDATE products SET average_cost = COALESCE((
                SELECT CASE
                    WHEN SUM(t.base_quantity) > 0
                    THEN ROUND(SUM(t.base_quantity * t.unit_cost) / SUM(t.base_quantity))
                    ELSE products.cost
                END
                FROM transactions t
                INNER JOIN invoices i ON t.invoice_id = i.id
                WHERE t.product_id = products.id
                AND t.delivered = true
                AND i.invocable_type != '{$customerClass}'
            ), products.cost)
        ");
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('average_cost');
        });
    }
};
