<?php

namespace App\Models;

use App\Enums\ExportStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportLog extends Model
{
    use BelongsToTenant, HasFactory;

    protected static function booted(): void
    {
        static::unguard();

        static::creating(function (ExportLog $exportLog) {
            $exportLog->status ??= ExportStatus::Queued;
        });
    }

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'status' => ExportStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === ExportStatus::Completed;
    }

    public function isFailed(): bool
    {
        return $this->status === ExportStatus::Failed;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function markProcessing(): void
    {
        $this->update(['status' => ExportStatus::Processing]);
    }

    public function markCompleted(string $path, string $filename): void
    {
        $this->update([
            'status' => ExportStatus::Completed,
            'path' => $path,
            'filename' => $filename,
            'expires_at' => now()->addDays(90),
        ]);
    }

    public function markFailed(string $message): void
    {
        $this->update([
            'status' => ExportStatus::Failed,
            'failure_message' => $message,
        ]);
    }
}
