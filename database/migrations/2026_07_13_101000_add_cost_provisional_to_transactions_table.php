<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flags a sale line whose cost was booked without a real cost basis (the
 * product had no known average cost at sale time). A later purchase back-fills
 * the real cost and clears the flag; profit reports are eventually consistent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('cost_provisional')->default(false)->after('unit_cost');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('cost_provisional');
        });
    }
};
