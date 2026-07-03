<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The per-tenant monotonic change cursor. `seq` values are drawn from
     * next_seq under a row lock so change-log appends serialize per tenant and
     * a consumer's cursor can never skip a change.
     */
    public function up(): void
    {
        Schema::create('tenant_sync_state', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->primary();
            $table->unsignedBigInteger('next_seq')->default(1);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            DB::table('tenant_sync_state')->insertOrIgnore([
                'tenant_id' => $tenantId,
                'next_seq' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_sync_state');
    }
};
