<?php

use App\Models\Preference;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('price')->default(0)->after('cost');
        });

        // Migrate existing data: price = cost * (1 + revenue_percent / 100)
        $tenantMarkups = Tenant::pluck('id')->mapWithKeys(function ($tenantId) {
            $percent = Preference::where('tenant_id', $tenantId)
                ->where('key', 'revenue_percent')
                ->value('value');

            return [$tenantId => (float) ($percent ?? 20)];
        });

        foreach ($tenantMarkups as $tenantId => $percent) {
            $multiplier = 1 + ($percent / 100);

            DB::table('products')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->update(['price' => DB::raw("ROUND(cost * {$multiplier})")]);
        }

        // Handle products without a tenant (shouldn't exist, but safe)
        DB::table('products')
            ->whereNull('tenant_id')
            ->update(['price' => DB::raw('ROUND(cost * 1.2)')]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
};
