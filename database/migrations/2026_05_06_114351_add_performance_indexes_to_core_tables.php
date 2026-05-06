<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['product_id', 'delivered']);
            $table->index('invoice_id');
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['tenant_id', 'invocable_type', 'payment_status']);
            $table->index(['invocable_type', 'invocable_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['direction', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'delivered']);
            $table->dropIndex(['invoice_id']);
            $table->dropIndex(['tenant_id', 'created_at']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'invocable_type', 'payment_status']);
            $table->dropIndex(['invocable_type', 'invocable_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['direction', 'paid_at']);
        });
    }
};
