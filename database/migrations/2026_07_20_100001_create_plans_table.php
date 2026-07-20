<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('name');                 // translatable: {"en": "...", "ar": "..."}
            $table->json('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort')->default(0);
            // Billing fields — schema-only until billing lands (no v1 UI).
            $table->decimal('price', 10, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('interval')->nullable();
            $table->timestamps();
        });

        // At most one default plan. Partial unique index — supported by both
        // Postgres (production) and SQLite (tests).
        DB::statement('CREATE UNIQUE INDEX plans_single_default ON plans (is_default) WHERE is_default');
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
