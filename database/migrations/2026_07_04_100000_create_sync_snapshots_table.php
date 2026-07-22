<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_snapshots', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('status', 12)->default('queued'); // App\Enums\SyncSnapshotStatus
            $table->unsignedBigInteger('cursor')->nullable(); // change_log seq watermark
            $table->string('path')->nullable();               // archive on the local disk
            $table->unsignedBigInteger('size')->nullable();   // archive bytes
            $table->json('manifest')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'device_id']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_snapshots');
    }
};
