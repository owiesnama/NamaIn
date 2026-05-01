<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupSetting extends Model
{
    protected static function booted(): void
    {
        static::unguard();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'retention_count' => 'integer',
        ];
    }

    public static function resolve(): self
    {
        return self::firstOrCreate([], [
            'is_enabled' => false,
            'frequency' => 'daily',
            'retention_count' => 5,
        ]);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function cronExpression(): string
    {
        return match ($this->frequency) {
            'daily' => '0 0 * * *',
            'weekly' => '0 0 * * 0',
            'monthly' => '0 0 1 * *',
            'custom' => $this->cron_expression ?? '0 0 * * *',
            default => '0 0 * * *',
        };
    }
}
