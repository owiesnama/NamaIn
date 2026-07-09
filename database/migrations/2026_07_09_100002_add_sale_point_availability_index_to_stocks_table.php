<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The POS product grid and favourites section order by stock quantity at a
     * single sale point (WHERE storage_id = ? ORDER BY quantity DESC). This
     * composite index lets Postgres resolve the storage's rows in quantity
     * order, so availability-first ordering reads from the index rather than
     * re-sorting the whole catalog in memory.
     */
    public function up(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->index(['storage_id', 'quantity'], 'stocks_storage_id_quantity_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropIndex('stocks_storage_id_quantity_index');
        });
    }
};
