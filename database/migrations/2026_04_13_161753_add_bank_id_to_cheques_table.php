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
        Schema::table('cheques', function (Blueprint $table) {
            $table->foreignId('bank_id')->nullable()->constrained()->onDelete('set null');
            $table->string('reference_number')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cheques', function (Blueprint $table) {
            $table->dropForeign(['bank_id']);
            $table->dropColumn(['bank_id', 'reference_number']);
        });
    }
};
