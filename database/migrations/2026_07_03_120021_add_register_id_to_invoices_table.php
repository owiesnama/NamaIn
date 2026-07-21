<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // The register that numbered this invoice: R0 for cloud-web, a device
            // register for offline sales. Nullable for legacy rows.
            $table->foreignId('register_id')->nullable()->after('pos_session_id')
                ->constrained('registers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('register_id');
        });
    }
};
