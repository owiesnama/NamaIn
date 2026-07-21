<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One append-only change ledger per DB. Entries carry identity only — no row
     * payload; pull resolves the current row live and deletes are tombstones.
     */
    public function up(): void
    {
        Schema::create('change_log', function (Blueprint $table) {
            $table->id();                                   // global monotonic; not the cursor
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('seq');              // per-tenant cursor
            $table->string('table_name', 64);
            $table->char('public_id', 26);                  // identity of the changed row
            $table->string('operation', 8);                 // create|update|delete
            $table->foreignId('source_device_id')->nullable()->constrained('devices');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->timestamp('changed_at');

            $table->unique(['tenant_id', 'seq']);
            $table->index(['tenant_id', 'seq']);                     // pull cursor scan
            $table->index(['tenant_id', 'table_name', 'public_id']); // compaction
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_log');
    }
};
