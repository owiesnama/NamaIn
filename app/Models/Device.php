<?php

namespace App\Models;

use App\Enums\DeviceStatus;
use App\Scopes\TenantScope;
use App\Traits\HasPublicId;
use App\Traits\RecordsChanges;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\HasApiTokens;

/**
 * A provisioned edge device (offline POS terminal). The device — not a user — is
 * the authenticated principal on the sync API: its Sanctum token is the channel
 * credential, and the bound tenant is resolved from `device.tenant_id`.
 *
 * Design choice (Design 02 §1.1): unlike every other business model, `Device`
 * does NOT extend {@see BaseModel} and therefore carries no {@see TenantScope}
 * global scope. It cannot: the sync guard must resolve the device from its token
 * *before* any tenant is bound (the tenant is derived from the device), and a
 * device belonging to tenant B must authenticate even when the host has another
 * tenant bound — token identity is global (`personal_access_tokens` is a global
 * table). A tenant scope would make token resolution fail closed to `1 = 0`. The
 * device is still tenant-owned data (`tenant_id` + {@see tenant()}) and remains
 * syncable via {@see RecordsChanges}; fleet queries filter the tenant explicitly.
 */
class Device extends Model implements AuthenticatableContract
{
    use AuthenticatableTrait;
    use HasApiTokens;
    use HasFactory;
    use HasPublicId;
    use RecordsChanges;

    /**
     * The Sanctum abilities every provisioned device token carries. One ability
     * per sync endpoint family; a device has no web-route access.
     *
     * @var list<string>
     */
    public const TOKEN_ABILITIES = ['sync:snapshot', 'sync:pull', 'sync:push', 'sync:attach'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => DeviceStatus::Pending->value,
    ];

    protected static function booted(): void
    {
        static::unguard();
    }

    /**
     * @return array<string, string>
     */
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

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isActive(): bool
    {
        return $this->status === DeviceStatus::Active;
    }

    public function isRevoked(): bool
    {
        return $this->status === DeviceStatus::Revoked;
    }
}
