<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['customers', 'suppliers'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->decimal('opening_debit', 15, 2)->default(0)->after('phone_number');
                $blueprint->decimal('opening_credit', 15, 2)->default(0)->after('opening_debit');
            });

            DB::table($table)->update([
                'opening_credit' => DB::raw('opening_balance'),
            ]);

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('opening_balance');
            });
        }
    }

    public function down(): void
    {
        foreach (['customers', 'suppliers'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->decimal('opening_balance', 15, 2)->default(0)->after('phone_number');
            });

            DB::table($table)->update([
                'opening_balance' => DB::raw('opening_credit'),
            ]);

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['opening_debit', 'opening_credit']);
            });
        }
    }
};
