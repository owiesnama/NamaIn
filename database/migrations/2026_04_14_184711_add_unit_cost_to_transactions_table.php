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
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('unit_cost', 15, 2)->nullable()->after('price');
        });

        // Update existing records with current product cost as default
        DB::table('transactions')
            ->update([
                'unit_cost' => DB::table('products')
                    ->whereColumn('products.id', 'transactions.product_id')
                    ->select('cost')
                    ->limit(1),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }
};
