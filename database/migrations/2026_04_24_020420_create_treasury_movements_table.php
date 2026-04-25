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
        Schema::create('treasury_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treasury_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->morphs('movable');
            $table->enum('reason', [
                'payment_received',
                'payment_refunded',
                'expense_paid',
                'transfer_in',
                'transfer_out',
                'pos_opening_float',
                'pos_closing_float',
                'cheque_deposited',
                'cheque_bounced',
                'manual_adjustment',
            ]);
            $table->bigInteger('amount');
            $table->unsignedBigInteger('balance_after');
            $table->string('notes')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['treasury_account_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treasury_movements');
    }
};
