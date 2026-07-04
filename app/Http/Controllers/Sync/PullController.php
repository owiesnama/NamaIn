<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\Sync\RowProjector;
use App\Services\Sync\SyncEntityDefinition;
use App\Services\Sync\SyncEntityMap;
use App\Services\Sync\SyncProtocol;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * GET /pull?cursor=&limit= (Design 02 §3): ordered change_log read, scope
 * filter (§4), collapse to the latest entry per (table, public_id) within the
 * page, live payload projection, tombstones with null payloads. Skipped
 * entries — unsynced tables, rows outside the device scope, rows superseded
 * or gone — still advance the cursor; re-pulling an older cursor re-sends the
 * same latest state, so applying is idempotent by construction.
 */
class PullController extends Controller
{
    private const DEFAULT_LIMIT = 200;

    private const MAX_LIMIT = 500;

    public function __invoke(Request $request, SyncEntityMap $entityMap, RowProjector $projector): JsonResponse
    {
        $validated = $request->validate([
            'cursor' => ['required', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
        ]);

        $cursor = (int) $validated['cursor'];
        $limit = (int) ($validated['limit'] ?? self::DEFAULT_LIMIT);

        /** @var Device $device */
        $device = $request->user('sync')->loadMissing('register');

        if (($expired = $this->cursorExpired($device, $cursor)) !== null) {
            return $expired;
        }

        $entries = DB::table('change_log')
            ->where('tenant_id', $device->tenant_id)
            ->where('seq', '>', $cursor)
            ->orderBy('seq')
            ->limit($limit + 1)
            ->get();

        $hasMore = $entries->count() > $limit;
        $entries = $entries->take($limit);
        $nextCursor = $entries->isEmpty() ? $cursor : (int) $entries->last()->seq;

        $changes = $this->resolveChanges($entries, $device, $entityMap, $projector);

        $device->forceFill([
            'last_pull_at' => now(),
            'last_acked_seq' => $cursor,
        ])->saveQuietly();

        return response()->json([
            'changes' => $changes,
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
            'protocol' => SyncProtocol::VERSION,
        ]);
    }

    /**
     * 409 cursor_expired (Design 04 §5.3): the cursor predates the oldest
     * retained change-log seq, so continuity can no longer be guaranteed and
     * the device must re-snapshot. The 30-day retention floor itself is
     * Phase 3; until compaction ships the log starts at seq 1 and this never
     * fires in practice.
     */
    private function cursorExpired(Device $device, int $cursor): ?JsonResponse
    {
        $oldestRetained = DB::table('change_log')
            ->where('tenant_id', $device->tenant_id)
            ->min('seq');

        if ($oldestRetained !== null && $cursor < (int) $oldestRetained - 1) {
            return response()->json([
                'error' => 'cursor_expired',
                'min_cursor' => (int) $oldestRetained,
            ], 409);
        }

        return null;
    }

    /**
     * @param  Collection<int, object>  $entries
     * @return list<array<string, mixed>>
     */
    private function resolveChanges(
        Collection $entries,
        Device $device,
        SyncEntityMap $entityMap,
        RowProjector $projector
    ): array {
        $latest = $entries
            ->groupBy(fn (object $entry) => "{$entry->table_name}|{$entry->public_id}")
            ->map(fn (Collection $group) => $group->sortBy('seq')->last());

        $changes = [];

        foreach ($latest->groupBy('table_name') as $table => $tableEntries) {
            $definition = $entityMap->pulledEntity($table);

            if ($definition === null) {
                continue; // outside device sync scope; the cursor advanced anyway
            }

            $liveEntries = $tableEntries->where('operation', '!=', 'delete');
            $payloads = $this->livePayloads($definition, $device, $liveEntries->pluck('public_id')->all(), $projector);

            foreach ($tableEntries as $entry) {
                if ($entry->operation === 'delete') {
                    $changes[] = $this->change($entry, null);

                    continue;
                }

                if (! isset($payloads[$entry->public_id])) {
                    continue; // out of the device scope, or deleted after this entry
                }

                $changes[] = $this->change($entry, $payloads[$entry->public_id]);
            }
        }

        usort($changes, fn (array $a, array $b) => $a['seq'] <=> $b['seq']);

        return $changes;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    private function change(object $entry, ?array $payload): array
    {
        return [
            'seq' => (int) $entry->seq,
            'table' => $entry->table_name,
            'op' => $entry->operation,
            'public_id' => $entry->public_id,
            'payload' => $payload,
        ];
    }

    /**
     * Resolve the surviving entries' current rows through the entity's scoped
     * query — scope filtering and payload projection in one pass.
     *
     * @param  list<string>  $publicIds
     * @return array<string, array<string, mixed>> public_id => payload
     */
    private function livePayloads(
        SyncEntityDefinition $definition,
        Device $device,
        array $publicIds,
        RowProjector $projector
    ): array {
        if ($publicIds === []) {
            return [];
        }

        $query = ($definition->query)($device);

        if ($query instanceof EloquentBuilder) {
            $query->whereIn($query->getModel()->qualifyColumn('public_id'), $publicIds);
            $query = $query->toBase();
        } else {
            $query->whereIn('public_id', $publicIds);
        }

        return collect($projector->project($definition, $query->get()))
            ->keyBy('public_id')
            ->all();
    }
}
