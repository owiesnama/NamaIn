<?php

namespace App\Models;

use App\Enums\DeviceStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends BaseModel
{
    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => DeviceStatus::Pending->value,
    ];

    protected function casts(): array
    {
        return [
            'status' => DeviceStatus::class,
            'pairing_expires_at' => 'datetime',
            'provisioned_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_pull_at' => 'datetime',
            'last_push_at' => 'datetime',
        ];
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }
}
