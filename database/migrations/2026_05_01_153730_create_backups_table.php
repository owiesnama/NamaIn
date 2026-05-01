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
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // tenant, full
            $table->string('format'); // sql, json, dump
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('filename');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('status')->default('pending'); // pending, running, completed, failed
            $table->text('error_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_scheduled')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
