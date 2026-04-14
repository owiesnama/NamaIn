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
            if (! Schema::hasColumn('cheques', 'invoice_id')) {
                $table->foreignId('invoice_id')->nullable()->after('chequeable_type');
            }
            if (! Schema::hasColumn('cheques', 'cleared_amount')) {
                $table->double('cleared_amount')->default(0)->after('amount');
            }
            if (! Schema::hasColumn('cheques', 'reference_number')) {
                $table->string('reference_number')->nullable()->after('type');
            }
            if (! Schema::hasColumn('cheques', 'notes')) {
                $table->text('notes')->nullable()->after('due');
            }
        });

        // Data migration: Map old statuses to new ones
        DB::table('cheques')->where('status', 'desirved')->update(['status' => 'issued']);
        DB::table('cheques')->where('status', 'paid')->update(['status' => 'cleared']);
        DB::table('cheques')->where('status', 'partialy_paid')->update(['status' => 'partially_cleared']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cheques', function (Blueprint $table) {
            $table->dropColumn(['invoice_id', 'cleared_amount', 'reference_number', 'notes']);
        });
    }
};
