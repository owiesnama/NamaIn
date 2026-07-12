<?php

use App\Enums\MovementType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('movement_type')->nullable()->after('reason')->index();
        });

        DB::table('stock_movements')
            ->select('id', 'reason')
            ->orderBy('id')
            ->chunkById(1000, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('stock_movements')
                        ->where('id', $row->id)
                        ->update(['movement_type' => MovementType::fromReason($row->reason)->value]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex(['movement_type']);
            $table->dropColumn('movement_type');
        });
    }
};
