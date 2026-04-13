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
        Schema::table('products', function (Blueprint $table) {
            $table->string('currency', 3)->nullable()->after('cost');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('currency', 3)->nullable()->after('status');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('currency', 3)->nullable()->after('amount');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->string('currency', 3)->nullable()->after('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('currency');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('currency');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('currency');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
