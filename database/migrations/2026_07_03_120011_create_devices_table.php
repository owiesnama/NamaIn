<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('register_id')->constrained('registers');
            $table->string('name');                        // "Front counter iPad"
            $table->string('status')->default('pending');  // pending|active|revoked (App\Enums\DeviceStatus)
            $table->string('pairing_code_hash')->nullable();
            $table->timestamp('pairing_expires_at')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_pull_at')->nullable();
            $table->timestamp('last_push_at')->nullable();
            $table->unsignedBigInteger('last_acked_seq')->default(0); // observability; client owns cursor
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
