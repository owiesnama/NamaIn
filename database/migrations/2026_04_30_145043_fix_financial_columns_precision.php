<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('total', 15, 2)->default(0)->change();
        });

        Schema::table('cheques', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->double('total')->change();
        });

        Schema::table('cheques', function (Blueprint $table) {
            $table->double('amount')->change();
        });
    }
};
