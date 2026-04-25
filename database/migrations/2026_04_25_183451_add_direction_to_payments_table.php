<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->enum('direction', ['in', 'out'])->default('in')->after('payment_method');
        });

        // Backfill existing rows
        DB::statement("UPDATE payments SET direction = 'in' WHERE payable_type LIKE '%Customer%'");
        DB::statement("UPDATE payments SET direction = 'out' WHERE payable_type LIKE '%Supplier%'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('direction');
        });
    }
};
