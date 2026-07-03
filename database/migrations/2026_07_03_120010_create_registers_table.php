<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registers', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // null storage_id marks the reserved cloud register (R0).
            $table->foreignId('storage_id')->nullable()->constrained('storages');
            $table->string('code', 8);            // 'R0','R1'... system-assigned, unique per tenant
            $table->string('label')->nullable();  // user-facing friendly name
            $table->boolean('is_cloud')->default(false); // true only for the reserved R0
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registers');
    }
};
