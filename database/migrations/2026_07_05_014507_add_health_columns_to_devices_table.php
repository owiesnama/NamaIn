<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Device health columns for PRD-04's dashboard (Design 02 §8.2, §8.5). The
 * cloud cannot see a device's outbox depth, so push and heartbeat carry the
 * device-reported pending backlog; heartbeat also carries the pilot SLO
 * counters (crashes, sessions) and the running app version.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->unsignedInteger('pending_count')->nullable()->after('last_acked_seq');
            $table->timestamp('oldest_pending_at')->nullable()->after('pending_count');
            $table->string('app_version')->nullable()->after('oldest_pending_at');
            $table->unsignedInteger('crash_count')->default(0)->after('app_version');
            $table->unsignedInteger('session_count')->default(0)->after('crash_count');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['pending_count', 'oldest_pending_at', 'app_version', 'crash_count', 'session_count']);
        });
    }
};
