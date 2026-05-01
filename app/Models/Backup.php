<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Backup extends Model
{
    /** @var string[] */
    protected $appends = ['size_human'];

    protected static function booted(): void
    {
        static::unguard();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'is_scheduled' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getPathAttribute(): string
    {
        return storage_path("app/backups/{$this->filename}");
    }

    public function getSizeHumanAttribute(): string
    {
        $bytes = $this->size;

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        }

        if ($bytes < 1073741824) {
            return round($bytes / 1048576, 1).' MB';
        }

        return round($bytes / 1073741824, 1).' GB';
    }

    /** @param Builder<self> $query */
    public function scopeScheduled(Builder $query): void
    {
        $query->where('is_scheduled', true);
    }

    public function markRunning(): void
    {
        $this->update(['status' => 'running']);
    }

    public function markCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'size' => file_exists($this->path) ? filesize($this->path) : 0,
            'completed_at' => now(),
        ]);
    }

    public function markFailed(string $message): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $message,
        ]);
    }
}
