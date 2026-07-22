<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The polymorphic reconciliation inbox (Design 04 §1.1). One tenant-scoped row
 * per divergence, owning the lifecycle/audit uniformly, with a `subject` morph
 * to one of four concrete detail tables. Written by the push pipeline in the
 * same transaction as its subject (R2); resolved by the tenant owner/manager.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_items', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Denormalized type for cheap listing/filtering without touching each subject table.
            $table->string('type', 24);                    // ReconciliationType enum value
            $table->morphs('subject');                      // subject_type / subject_id -> detail row

            // The "who / which" (device time and sync origin), all nullable for robustness.
            $table->foreignId('device_id')->nullable()->constrained('devices');
            $table->foreignId('register_id')->nullable()->constrained('registers');
            $table->foreignId('actor_user_id')->nullable()->constrained('users'); // offline cashier

            // The "when": device/business time vs server sync time (FR-1, FR-10).
            $table->timestamp('occurred_at');               // as reported by the device (business time)
            $table->timestamp('detected_at');               // server receipt time (push landed)

            // Lifecycle + resolution audit (R3).
            $table->string('status', 12)->default('open');  // open | resolved
            $table->string('resolution', 24)->nullable();   // ResolutionKind enum (per-type set)
            $table->text('resolution_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'status', 'type']); // inbox: open items by type
            $table->index(['tenant_id', 'device_id']);      // device drill-down
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_items');
    }
};
