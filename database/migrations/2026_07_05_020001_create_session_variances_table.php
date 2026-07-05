<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offline session cash variance (Design 04 §1.2, FR-4). One row per
 * offline-originated session+drawer whose declared close disagreed with the
 * expected drawer balance. Amounts are signed integer minor units. The
 * resolution lifecycle lives on `reconciliation_items`, so no resolved_at/by.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_variances', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices');
            $table->foreignId('register_id')->constrained('registers');
            $table->foreignId('pos_session_id')->constrained('pos_sessions');
            $table->foreignId('treasury_account_id')->constrained('treasury_accounts'); // the drawer
            $table->bigInteger('expected_amount');    // drawer currentBalance() before adjustment (minor units)
            $table->bigInteger('declared_amount');    // closing_float the cashier counted (minor units)
            $table->bigInteger('variance_amount');    // declared - expected (signed minor units)
            $table->foreignId('adjustment_movement_id')->nullable()->constrained('treasury_movements');
            $table->timestamp('occurred_at');         // device close time
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_variances');
    }
};
