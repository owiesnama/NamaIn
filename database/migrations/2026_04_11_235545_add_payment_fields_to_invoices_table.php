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
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('payment_method')->default('credit')->after('status');
            $table->string('payment_status')->default('unpaid')->after('payment_method');
            $table->decimal('paid_amount', 15, 2)->default(0)->after('payment_status');
            $table->decimal('discount', 15, 2)->default(0)->after('paid_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'payment_status', 'paid_amount', 'discount']);
        });
    }
};
