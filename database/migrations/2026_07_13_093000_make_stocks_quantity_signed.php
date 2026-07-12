<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Allow negative stock balances so free-form tenants can oversell.
 *
 * Only MySQL/MariaDB enforce the UNSIGNED constraint. Postgres maps
 * unsignedBigInteger to a (signed) bigint already, and SQLite is dynamically
 * typed, so both already permit negatives — this migration is a no-op there.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        Schema::table('stocks', function (Blueprint $table) {
            $table->bigInteger('quantity')->default(0)->change();
        });
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        Schema::table('stocks', function (Blueprint $table) {
            $table->unsignedBigInteger('quantity')->default(0)->change();
        });
    }
};
