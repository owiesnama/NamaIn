<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'is_global_favorite')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('is_global_favorite')->default(false);
            });
        }

        // Partial index: global favourites are a tiny slice of the catalog, so a
        // partial index on the "true" rows keeps the favourites lookup cheap
        // without bloating writes on the common (false) path.
        DB::statement('CREATE INDEX IF NOT EXISTS products_is_global_favorite_index ON products (is_global_favorite) WHERE is_global_favorite = true');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS products_is_global_favorite_index');

        if (Schema::hasColumn('products', 'is_global_favorite')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('is_global_favorite');
            });
        }
    }
};
