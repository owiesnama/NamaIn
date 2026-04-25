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
        Schema::create('treasury_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_account_id')->constrained('treasury_accounts');
            $table->foreignId('to_account_id')->constrained('treasury_accounts');
            $table->foreignId('created_by')->constrained('users');
            $table->unsignedBigInteger('amount');
            $table->string('notes')->nullable();
            $table->timestamp('transferred_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treasury_transfers');
    }
};
