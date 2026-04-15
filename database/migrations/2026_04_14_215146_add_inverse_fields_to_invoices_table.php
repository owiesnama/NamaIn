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
        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('is_inverse')->default(false);
            $table->foreignId('parent_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->text('inverse_reason')->nullable();
            $table->index('parent_invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['parent_invoice_id']);
            $table->dropColumn(['is_inverse', 'parent_invoice_id', 'inverse_reason']);
        });
    }
};
