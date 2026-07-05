<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Oversell records (Design 02 §6.1): a cloud-only, server-derived row created
 * when a replayed sale force-deducts stock past on-hand. The resolution
 * lifecycle lives on PRD-04's reconciliation inbox, so no resolved_at/by here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oversell_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices');
            $table->foreignId('storage_id')->constrained('storages');
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('invoice_id')->constrained('invoices');
            $table->integer('oversold_qty');
            $table->integer('on_hand_before');
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oversell_reconciliations');
    }
};
