<?php

namespace App\Models;

use App\Enums\SyncSnapshotStatus;
use App\Traits\BelongsToTenant;
use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A queued device bootstrap snapshot (Design 02 §2). Infrastructure metadata,
 * not syncable business data — tenant-scoped like ExportLog but never recorded
 * in the change log. `public_id` is the wire snapshot_id.
 */
class SyncSnapshot extends Model
{
    use BelongsToTenant, HasFactory, HasPublicId;

    public const TTL_HOURS = 48;

    protected static function booted(): void
    {
        static::unguard();

        static::creating(function (SyncSnapshot $snapshot) {
            $snapshot->status ??= SyncSnapshotStatus::Queued;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SyncSnapshotStatus::class,
            'manifest' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function isReady(): bool
    {
        return $this->status === SyncSnapshotStatus::Ready;
    }

    public function directory(): string
    {
        return "sync-snapshots/{$this->tenant_id}/{$this->public_id}";
    }

    public function markProcessing(): void
    {
        $this->update(['status' => SyncSnapshotStatus::Processing]);
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    public function markReady(string $path, int $size, int $cursor, array $manifest): void
    {
        $this->update([
            'status' => SyncSnapshotStatus::Ready,
            'path' => $path,
            'size' => $size,
            'cursor' => $cursor,
            'manifest' => $manifest,
        ]);
    }

    public function markFailed(string $message): void
    {
        $this->update([
            'status' => SyncSnapshotStatus::Failed,
            'failure_message' => $message,
        ]);
    }
}
