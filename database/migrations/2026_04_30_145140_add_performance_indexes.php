<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['invocable_type', 'invocable_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['invoice_id', 'direction']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['product_id', 'delivered']);
        });

        Schema::table('treasury_movements', function (Blueprint $table) {
            $table->index(['treasury_account_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['invocable_type', 'invocable_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['invoice_id', 'direction']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'delivered']);
        });

        Schema::table('treasury_movements', function (Blueprint $table) {
            $table->dropIndex(['treasury_account_id', 'id']);
        });
    }
};
