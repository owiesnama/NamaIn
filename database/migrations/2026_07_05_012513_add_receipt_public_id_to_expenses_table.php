<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A pushed expense (Design 02 §5.3) references its receipt by the device-minted
 * `receipt_public_id`; the separate POST /attachments upload (§7.4) links the
 * stored file back by matching this column, so the receipt can arrive before or
 * after the expense mutation lands.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->char('receipt_public_id', 26)->nullable()->unique()->after('receipt_path');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('receipt_public_id');
        });
    }
};
