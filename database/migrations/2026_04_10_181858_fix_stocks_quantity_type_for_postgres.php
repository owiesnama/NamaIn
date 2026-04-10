<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        // Only needed when migrating an existing database. A fresh install will
        // already have the correct integer type from the original migration.
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE stocks ALTER COLUMN quantity TYPE BIGINT USING quantity::BIGINT');
            DB::statement('ALTER TABLE stocks ALTER COLUMN quantity SET DEFAULT 0');
        } else {
            Schema::table('stocks', function (Blueprint $table) {
                $table->unsignedBigInteger('quantity')->default(0)->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->string('quantity')->change();
        });
    }
};
