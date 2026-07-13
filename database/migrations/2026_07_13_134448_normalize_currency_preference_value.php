<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The settings UI now stores the currency through a fixed ISO 4217
     * <select> list. Normalise any previously free-text value (which was
     * entered/stored ad hoc) so existing rows line up with a valid option:
     * upper-case + trim, and fall back to the app default for anything that
     * isn't a 3-letter code. Runs across every tenant's row uniformly.
     */
    public function up(): void
    {
        DB::table('preferences')
            ->where('key', 'currency')
            ->whereNotNull('value')
            ->update(['value' => DB::raw('UPPER(TRIM(value))')]);

        DB::table('preferences')
            ->where('key', 'currency')
            ->where(function ($query) {
                $query->whereNull('value')
                    ->orWhereRaw('LENGTH(TRIM(value)) <> 3');
            })
            ->update(['value' => 'SDG']);
    }

    /**
     * Normalisation is not reversible; the original ad-hoc values are lost.
     */
    public function down(): void
    {
        //
    }
};
