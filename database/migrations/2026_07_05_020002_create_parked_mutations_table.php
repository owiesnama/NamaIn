<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A terminally-rejected push mutation (Design 04 §1.2). Non-retriable rejections
 * (validation_failed, session_closed, tenant_mismatch) park the raw envelope so
 * the owner/support can inspect and a fix-and-replay or discard is possible.
 * Retriable rejections (unknown_reference) are never parked. One row per
 * mutation (unique idempotency_key), so a re-push never double-parks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parked_mutations', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices');
            $table->string('mutation_type', 40);       // sale.create, expense.create, ...
            $table->string('idempotency_key');
            $table->string('rejection_reason', 32);    // RejectionReason enum value
            $table->text('rejection_message')->nullable();
            $table->json('envelope');                  // the full Mutation DTO as received (audit/replay)
            $table->timestamp('occurred_at');          // mutation.occurred_at (device time)
            $table->timestamps();
            $table->unique(['tenant_id', 'idempotency_key']); // one parked row per mutation
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parked_mutations');
    }
};
