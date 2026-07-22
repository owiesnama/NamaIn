<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sync audit trail (Design 02 §8.1): one row per device-authenticated request
 * with its endpoint, cursor window (pull), mutation counts (push), and latency.
 * `client_pushed_at` (§8.5) is when the device worker began the push.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('device_id')->constrained();
            $table->string('endpoint', 24);
            $table->unsignedBigInteger('cursor_from')->nullable();
            $table->unsignedBigInteger('cursor_to')->nullable();
            $table->unsignedInteger('mutation_count')->default(0);
            $table->unsignedInteger('applied_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('client_pushed_at')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
