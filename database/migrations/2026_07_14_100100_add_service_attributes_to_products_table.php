<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Service-specific attributes on products. They are meaningful only when
     * `type = service`; every existing physical row reads them at their default
     * (no duration, non-booking, non-on-site, non-overlap), i.e. today's meaning.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'duration_minutes')) {
                $table->unsignedInteger('duration_minutes')->nullable()->after('price');
            }
            if (! Schema::hasColumn('products', 'requires_booking')) {
                $table->boolean('requires_booking')->default(false)->after('duration_minutes');
            }
            if (! Schema::hasColumn('products', 'on_site')) {
                $table->boolean('on_site')->default(false)->after('requires_booking');
            }
            if (! Schema::hasColumn('products', 'allow_overlap')) {
                $table->boolean('allow_overlap')->default(false)->after('on_site');
            }
            if (! Schema::hasColumn('products', 'travel_buffer_minutes')) {
                $table->unsignedInteger('travel_buffer_minutes')->nullable()->after('allow_overlap');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'duration_minutes',
                'requires_booking',
                'on_site',
                'allow_overlap',
                'travel_buffer_minutes',
            ]);
        });
    }
};
