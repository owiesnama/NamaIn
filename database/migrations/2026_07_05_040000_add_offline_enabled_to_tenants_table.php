<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant offline feature flag (Design 04 §6.5, R15). Default `false` — a
 * controlled pilot rollout + kill switch, mirroring `tenants.is_active`. Gates
 * web device enrollment and `POST /provision` (`403 offline_disabled`); an
 * already-provisioned device keeps working if the flag is later turned off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('offline_enabled')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('offline_enabled');
        });
    }
};
