<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateSnapshotJob;
use App\Models\Device;
use App\Models\SyncSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Snapshot delivery (Design 02 §2.3): queue → poll → ranged download. Every
 * lookup is scoped to the authenticated device, so a snapshot id from another
 * tenant or device 404s.
 */
class SnapshotController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user('sync');

        $snapshot = SyncSnapshot::create([
            'tenant_id' => $device->tenant_id,
            'device_id' => $device->id,
            'expires_at' => now()->addHours(SyncSnapshot::TTL_HOURS),
        ]);

        GenerateSnapshotJob::dispatch($snapshot);

        return response()->json([
            'snapshot_id' => $snapshot->public_id,
            'status' => $snapshot->status->value,
        ], 202);
    }

    public function show(Request $request, string $snapshotId): JsonResponse
    {
        $snapshot = $this->deviceSnapshot($request, $snapshotId);

        if (! $snapshot->isReady()) {
            return response()->json(['status' => $snapshot->status->value]);
        }

        return response()->json([
            'status' => $snapshot->status->value,
            'download_url' => route('sync.snapshot.download', $snapshot->public_id),
            'manifest_url' => route('sync.snapshot.manifest', $snapshot->public_id),
            'size' => $snapshot->size,
            'cursor' => $snapshot->cursor,
        ]);
    }

    public function manifest(Request $request, string $snapshotId): JsonResponse
    {
        $snapshot = $this->deviceSnapshot($request, $snapshotId);

        abort_unless($snapshot->isReady(), 409);

        return response()->json($snapshot->manifest);
    }

    /**
     * BinaryFileResponse serves single-range requests natively, giving the
     * client resumable downloads (FR-6).
     */
    public function download(Request $request, string $snapshotId): BinaryFileResponse
    {
        $snapshot = $this->deviceSnapshot($request, $snapshotId);

        abort_unless($snapshot->isReady(), 409);

        return response()->file(Storage::disk('local')->path($snapshot->path), [
            'Content-Type' => 'application/gzip',
            'Accept-Ranges' => 'bytes',
            'Content-Disposition' => 'attachment; filename="snapshot.tar.gz"',
        ]);
    }

    private function deviceSnapshot(Request $request, string $snapshotId): SyncSnapshot
    {
        /** @var Device $device */
        $device = $request->user('sync');

        return SyncSnapshot::where('public_id', $snapshotId)
            ->where('device_id', $device->id)
            ->firstOrFail();
    }
}
