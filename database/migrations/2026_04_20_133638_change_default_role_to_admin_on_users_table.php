<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Already applied in production. For fresh DBs, the create_users_table
        // migration handles the role column definition.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
