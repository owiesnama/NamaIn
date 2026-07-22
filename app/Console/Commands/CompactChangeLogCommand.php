<?php

namespace App\Console\Commands;

use App\Enums\DeviceStatus;
use App\Models\Device;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Change-log compaction (Design 04 §5.3, R13). Per tenant, collapse superseded
 * (table, public_id) entries — keeping the latest — but only ones that are BOTH
 * older than the 30-day retention floor AND at or below `min(last_acked_seq)`
 * among active devices. The floor covers realistic offline gaps (a long
 * weekend, a seasonal closure) so lagging devices still resume by cheap
 * incremental pull; the min-cursor guard means a still-lagging device never
 * loses an entry it has not yet seen. Rows past the horizon re-snapshot instead.
 */
class CompactChangeLogCommand extends Command
{
    protected $signature = 'sync:compact-change-log {--days=30 : Retention floor in days}';

    protected $description = 'Collapse superseded change-log entries older than the retention floor and below the min active device cursor';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) $this->option('days'));
        $pruned = 0;

        Tenant::query()->get()->each(function (Tenant $tenant) use ($cutoff, &$pruned): void {
            $horizon = $this->safeHorizon($tenant);

            $query = DB::table('change_log as c')
                ->where('c.tenant_id', $tenant->id)
                ->where('c.changed_at', '<', $cutoff)
                ->where('c.seq', '<=', $horizon)
                ->whereExists(fn (Builder $exists) => $exists
                    ->from('change_log as n')
                    ->whereColumn('n.tenant_id', 'c.tenant_id')
                    ->whereColumn('n.table_name', 'c.table_name')
                    ->whereColumn('n.public_id', 'c.public_id')
                    ->whereColumn('n.seq', '>', 'c.seq'));

            $pruned += $query->delete();
        });

        $this->info("Compacted {$pruned} superseded change-log entries.");

        return self::SUCCESS;
    }

    /**
     * The highest seq safe to prune at or below: the minimum acked cursor among
     * active devices. With no active devices there is no lagging reader, so only
     * the 30-day floor applies (no cursor ceiling).
     */
    private function safeHorizon(Tenant $tenant): int
    {
        $minAcked = Device::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', DeviceStatus::Active)
            ->min('last_acked_seq');

        return $minAcked === null ? PHP_INT_MAX : (int) $minAcked;
    }
}
