<?php

namespace App\Models;

use App\Enums\ExportStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportLog extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        static::unguard();

        static::creating(function (ImportLog $importLog) {
            $importLog->status ??= ExportStatus::Queued->value;
        });
    }

    protected function casts(): array
    {
        return [
            'status' => ExportStatus::class,
            'rows_imported' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markProcessing(): void
    {
        $this->update(['status' => ExportStatus::Processing]);
    }

    public function markCompleted(int $rowsImported): void
    {
        $this->update([
            'status' => ExportStatus::Completed,
            'rows_imported' => $rowsImported,
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
