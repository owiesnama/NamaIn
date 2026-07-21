<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Central idempotency ledger for pushed mutations. One row per
     * (tenant, idempotency_key) records the mutation's outcome so a replay
     * returns the original result with zero further writes.
     */
    public function up(): void
    {
        Schema::create('sync_idempotency', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices');
            $table->string('idempotency_key');            // client-generated (ULID-based)
            $table->string('mutation_type', 40);          // 'sale.create', 'expense.create', ...
            $table->char('result_public_id', 26)->nullable(); // the entity the mutation produced
            $table->string('status', 12);                 // applied|rejected
            $table->json('result')->nullable();           // serialized result for exact replay
            $table->timestamps();

            $table->unique(['tenant_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_idempotency');
    }
};
