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
        Schema::table('treasury_movements', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('treasury_transfers', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('quote_items', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        DB::statement('
            UPDATE treasury_movements
            SET tenant_id = (
                SELECT tenant_id FROM treasury_accounts
                WHERE treasury_accounts.id = treasury_movements.treasury_account_id
            )
        ');

        DB::statement('
            UPDATE treasury_transfers
            SET tenant_id = (
                SELECT tenant_id FROM treasury_accounts
                WHERE treasury_accounts.id = treasury_transfers.from_account_id
            )
        ');

        DB::statement('
            UPDATE quote_items
            SET tenant_id = (
                SELECT tenant_id FROM quotes
                WHERE quotes.id = quote_items.quote_id
            )
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('treasury_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('treasury_transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('quote_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
