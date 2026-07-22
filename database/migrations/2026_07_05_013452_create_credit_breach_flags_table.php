<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credit-breach records (Design 02 §6.2): a cloud-only, server-derived row
 * created when a credit sale pushes the customer's balance past their cached
 * credit_limit. Never rejected. Resolution lifecycle lives on PRD-04's
 * reconciliation inbox, so no resolved_at/by here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_breach_flags', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices');
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('invoice_id')->constrained('invoices');
            $table->bigInteger('credit_limit');
            $table->bigInteger('balance_after');
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_breach_flags');
    }
};
