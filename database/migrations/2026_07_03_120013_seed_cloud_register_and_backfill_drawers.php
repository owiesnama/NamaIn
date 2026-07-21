<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Give every existing tenant the reserved cloud register R0 (storage-less,
     * is_cloud) that owns all cloud-web numbering, then point each existing
     * per-sale-point cash drawer at its tenant's R0.
     */
    public function up(): void
    {
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $registerId = DB::table('registers')
                ->where('tenant_id', $tenantId)
                ->where('code', 'R0')
                ->value('id');

            if (! $registerId) {
                $registerId = DB::table('registers')->insertGetId([
                    'public_id' => strtolower((string) Str::ulid()),
                    'tenant_id' => $tenantId,
                    'storage_id' => null,
                    'code' => 'R0',
                    'label' => 'Cloud',
                    'is_cloud' => true,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('treasury_accounts')
                ->where('tenant_id', $tenantId)
                ->whereNotNull('sale_point_id')
                ->whereNull('register_id')
                ->update(['register_id' => $registerId]);
        }
    }

    public function down(): void
    {
        DB::table('treasury_accounts')->update(['register_id' => null]);
        DB::table('registers')->where('code', 'R0')->delete();
    }
};
