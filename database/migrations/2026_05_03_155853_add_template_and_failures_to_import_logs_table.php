<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('import_logs', function (Blueprint $table) {
            $table->string('template')->default('default')->after('import_type');
            $table->json('failures')->nullable()->after('failure_message');
        });
    }

    public function down(): void
    {
        Schema::table('import_logs', function (Blueprint $table) {
            $table->dropColumn(['template', 'failures']);
        });
    }
};
