<?php

namespace App\Traits;

use App\Models\ChangeLog;

/**
 * Channel A of the change-capture design: every Eloquent create/update/delete of
 * a syncable model appends a change-log entry inside the same transaction. A
 * soft delete fires `deleted` and is recorded as a tombstone (the device should
 * stop showing the row); a restore re-surfaces the row as an update.
 *
 * Raw query-builder writes bypass these events and are covered explicitly by
 * Channel B (see Storage/Product); an architecture test enforces the boundary.
 */
trait RecordsChanges
{
    public static function bootRecordsChanges(): void
    {
        // registerModelEvent (rather than the static event helpers) avoids the
        // magic-static __callStatic path, which would re-enter model booting for
        // events like `restored` while this boot method is still running.
        static::registerModelEvent('created', function ($model): void {
            ChangeLog::record($model->getTable(), $model->public_id, 'create', $model->tenant_id);
        });

        static::registerModelEvent('updated', function ($model): void {
            ChangeLog::record($model->getTable(), $model->public_id, 'update', $model->tenant_id);
        });

        static::registerModelEvent('deleted', function ($model): void {
            ChangeLog::record($model->getTable(), $model->public_id, 'delete', $model->tenant_id);
        });

        static::registerModelEvent('restored', function ($model): void {
            ChangeLog::record($model->getTable(), $model->public_id, 'update', $model->tenant_id);
        });
    }
}
