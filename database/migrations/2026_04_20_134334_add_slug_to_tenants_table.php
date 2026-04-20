<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('tenants', 'slug')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('slug')->default('')->after('name');
            });
        }

        // Generate slugs for existing tenants
        foreach (DB::table('tenants')->get() as $tenant) {
            DB::table('tenants')
                ->where('id', $tenant->id)
                ->update(['slug' => Str::slug($tenant->name ?: 'tenant-'.$tenant->id)]);
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->unique('slug');
        });

        Schema::dropIfExists('domains');

        if (Schema::hasColumn('tenants', 'data')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('data');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('slug');
            $table->json('data')->nullable();
        });
    }
};
