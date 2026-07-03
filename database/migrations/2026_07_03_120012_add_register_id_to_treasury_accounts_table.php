<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treasury_accounts', function (Blueprint $table) {
            // A register's cash drawer is the treasury account carrying its id.
            // Nullable and additive: cloud-web lookups still resolve by sale_point_id.
            $table->foreignId('register_id')->nullable()->after('sale_point_id')
                ->constrained('registers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('treasury_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('register_id');
        });
    }
};
