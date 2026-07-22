<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Device fleet-management columns (Design 04 §4.2, §5.1). `revoked_at` +
 * `revoked_unsynced_count` record a revocation and its last-known outbox depth
 * (≈ N items may be lost); `clock_skew_seconds` is the coarse server_now −
 * client_time estimate that drives the `skewed` health state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->timestamp('revoked_at')->nullable()->after('provisioned_at');
            $table->unsignedInteger('revoked_unsynced_count')->nullable()->after('revoked_at');
            $table->integer('clock_skew_seconds')->nullable()->after('session_count');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['revoked_at', 'revoked_unsynced_count', 'clock_skew_seconds']);
        });
    }
};
