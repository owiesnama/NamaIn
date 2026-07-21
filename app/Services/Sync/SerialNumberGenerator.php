<?php

namespace App\Services\Sync;

use App\Models\Register;
use Illuminate\Support\Facades\DB;

/**
 * Allocates human-readable invoice serials of the form
 * `{INV|RET}-{SA|SU}-{yy}-{code}-{seq}` (e.g. `INV-SA-26-R0-00145`).
 *
 * The sequence comes from a per-(register, series, year) counter locked with
 * `lockForUpdate`, never from a table PK. A register is single-writer by
 * construction (cloud R0 vs one device per register), so counters never
 * contend across nodes and the printed number is final.
 */
class SerialNumberGenerator
{
    public function generate(Register $register, string $series, int $year): string
    {
        $sequence = $this->nextSequence($register, $series, $year);

        return sprintf(
            '%s-%02d-%s-%05d',
            $series,
            $year % 100,
            $register->code,
            $sequence
        );
    }

    private function nextSequence(Register $register, string $series, int $year): int
    {
        return DB::transaction(function () use ($register, $series, $year) {
            $key = [
                'tenant_id' => $register->tenant_id,
                'register_id' => $register->id,
                'series' => $series,
                'year' => $year,
            ];

            $row = DB::table('register_serials')->where($key)->lockForUpdate()->first();

            if (! $row) {
                DB::table('register_serials')->insert($key + [
                    'last_seq' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return 1;
            }

            $next = $row->last_seq + 1;

            DB::table('register_serials')->where('id', $row->id)->update([
                'last_seq' => $next,
                'updated_at' => now(),
            ]);

            return $next;
        });
    }
}
