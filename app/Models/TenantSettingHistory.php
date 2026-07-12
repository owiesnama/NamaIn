<?php

namespace App\Models;

use App\Exceptions\ImmutableRecordException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An append-only record of a change to a tenant setting (e.g. inventory
 * strategy). Reports read this to explain rule changes that happened
 * mid-history. Rows are never updated or deleted.
 */
class TenantSettingHistory extends BaseModel
{
    protected $table = 'tenant_settings_history';

    protected static function booted(): void
    {
        parent::booted();

        static::updating(function () {
            throw new ImmutableRecordException('Tenant setting history', 'updated');
        });

        static::deleting(function () {
            throw new ImmutableRecordException('Tenant setting history', 'deleted');
        });
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
