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
        Schema::create('customer_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('treasury_account_id')->constrained();
            $table->foreignId('created_by')->constrained('users');
            $table->decimal('amount', 12, 2);
            $table->decimal('settled_amount', 12, 2)->default(0);
            $table->string('status')->default('outstanding');
            $table->text('notes')->nullable();
            $table->timestamp('given_at');
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();

            $table->index(['customer_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_advances');
    }
};
