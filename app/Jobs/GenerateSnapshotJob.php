<?php

namespace App\Jobs;

use App\Models\Device;
use App\Models\Preference;
use App\Models\SyncSnapshot;
use App\Models\Tenant;
use App\Services\Sync\RowProjector;
use App\Services\Sync\SyncEntityDefinition;
use App\Services\Sync\SyncEntityMap;
use App\Services\Sync\SyncProtocol;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PharData;

/**
 * Builds a device bootstrap snapshot (Design 02 §2): one gzipped JSONL file
 * per entity + manifest.json, bundled into snapshot.tar.gz on the local disk.
 *
 * Consistency (FR-4): the cursor is `tenant_sync_state.next_seq - 1` read
 * before exporting. Change-log appends serialize per tenant behind the
 * counter's row lock, so every change with seq <= cursor is committed when the
 * counter shows it — the export is a consistent cut and the first pull with
 * `cursor = manifest.cursor` continues with no gap. Rows committed during the
 * export carry seq > cursor and are re-delivered by pull; the client upsert
 * makes that idempotent.
 */
class GenerateSnapshotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    private const PROJECTION_CHUNK = 500;

    public function __construct(public SyncSnapshot $snapshot)
    {
        $this->onQueue('exports');
    }

    public function handle(SyncEntityMap $entityMap, RowProjector $projector): void
    {
        $this->bindTenantContext();

        $this->snapshot->markProcessing();

        $device = Device::with('register.storage')->findOrFail($this->snapshot->device_id);
        $cursor = $this->cursorWatermark();
        $directory = $this->snapshot->directory();

        Storage::disk('local')->makeDirectory($directory);

        $entities = [];

        foreach ($entityMap->snapshotEntities() as $definition) {
            $file = "{$definition->table}.jsonl.gz";
            $count = $this->writeEntityFile($definition, $projector, $device, "{$directory}/{$file}");

            $entities[] = ['table' => $definition->table, 'file' => $file, 'count' => $count];
        }

        $manifest = [
            'snapshot_id' => $this->snapshot->public_id,
            'tenant' => $device->tenant->public_id,
            'register' => $device->register->code,
            'storage' => $device->register->storage?->public_id,
            'cursor' => $cursor,
            'protocol' => SyncProtocol::VERSION,
            'entities' => $entities,
        ];

        Storage::disk('local')->put(
            "{$directory}/manifest.json",
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $archivePath = $this->buildArchive($directory);

        $this->snapshot->markReady(
            $archivePath,
            (int) Storage::disk('local')->size($archivePath),
            $cursor,
            $manifest
        );
    }

    public function failed(\Throwable $exception): void
    {
        report($exception);

        $this->snapshot->markFailed(__('Snapshot generation failed. Please request a new snapshot.'));
    }

    /**
     * The committed per-tenant change counter is only advanced at commit, so
     * `next_seq - 1` is a watermark below which every change is visible.
     */
    private function cursorWatermark(): int
    {
        $nextSeq = DB::table('tenant_sync_state')
            ->where('tenant_id', $this->snapshot->tenant_id)
            ->value('next_seq');

        return max(0, (int) ($nextSeq ?? 1) - 1);
    }

    private function writeEntityFile(
        SyncEntityDefinition $definition,
        RowProjector $projector,
        Device $device,
        string $path
    ): int {
        $query = ($definition->query)($device);

        if ($query instanceof EloquentBuilder) {
            $query = $query->toBase();
        }

        $rows = $query->get();
        $lines = '';
        $count = 0;

        foreach ($rows->chunk(self::PROJECTION_CHUNK) as $chunk) {
            foreach ($projector->project($definition, $chunk->values()) as $projected) {
                $lines .= json_encode($projected, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
                $count++;
            }
        }

        Storage::disk('local')->put($path, gzencode($lines, 9));

        return $count;
    }

    /**
     * Bundle the entity files + manifest into snapshot.tar.gz for a single
     * resumable download. PharData tar creation works regardless of
     * phar.readonly.
     */
    private function buildArchive(string $directory): string
    {
        $absoluteDirectory = Storage::disk('local')->path($directory);
        $tarPath = "{$absoluteDirectory}/snapshot.tar";

        $archive = new PharData($tarPath);

        foreach (Storage::disk('local')->files($directory) as $file) {
            $archive->addFile(Storage::disk('local')->path($file), basename($file));
        }

        $archive->compress(\Phar::GZ);
        unset($archive);
        \Phar::unlinkArchive($tarPath);

        return "{$directory}/snapshot.tar.gz";
    }

    private function bindTenantContext(): void
    {
        $tenant = Tenant::find($this->snapshot->tenant_id);

        if ($tenant) {
            app()->instance('currentTenant', $tenant);

            $preferences = Preference::where('tenant_id', $tenant->id)->pluck('value', 'key')->toArray();
            app()->setLocale($preferences['language'] ?? config('app.locale'));
        }
    }
}
