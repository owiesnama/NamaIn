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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status')->default('confirmed');
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('base_price')->default(0); // minor units (MoneyCast)
            $table->unsignedInteger('total')->default(0); // minor units (MoneyCast)
            $table->timestamps();
            $table->softDeletes();

            // Serves B2 overlap detection and the B3 calendar range-scan.
            $table->index(['service_product_id', 'status', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
