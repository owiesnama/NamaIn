<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('register_serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('register_id')->constrained('registers');
            $table->string('series', 8);          // 'INV-SA','INV-SU','RET-SA','RET-SU'
            $table->unsignedSmallInteger('year'); // stored 4-digit
            $table->unsignedBigInteger('last_seq')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'register_id', 'series', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('register_serials');
    }
};
