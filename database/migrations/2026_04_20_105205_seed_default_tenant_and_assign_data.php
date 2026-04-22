<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Handled by the slug migration and manual seeding for production.
        // In fresh test DBs, there's no data to migrate.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
