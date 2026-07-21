<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Register extends BaseModel
{
    /** The reserved cloud register code, one per tenant. */
    public const CLOUD_CODE = 'R0';

    protected function casts(): array
    {
        return [
            'is_cloud' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function storage(): BelongsTo
    {
        return $this->belongsTo(Storage::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function isCloud(): bool
    {
        return (bool) $this->is_cloud;
    }

    /**
     * The reserved cloud register (R0) for a tenant — created on demand so every
     * tenant, however provisioned, has one. All cloud-web numbering runs on it.
     */
    public static function cloudRegisterFor(Tenant|int $tenant): self
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return static::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenantId, 'code' => self::CLOUD_CODE],
            [
                'storage_id' => null,
                'label' => 'Cloud',
                'is_cloud' => true,
                'is_active' => true,
            ]
        );
    }
}
