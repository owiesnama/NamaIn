<?php

namespace App\Traits;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Assigns a globally unique, offline-mintable ULID to the model's `public_id`
 * column on create. This is the sync identity used across the offline boundary;
 * it never replaces the integer primary key or any foreign key.
 */
trait HasPublicId
{
    /**
     * Tables already confirmed to have the `public_id` column. Presence is
     * permanent once a migration adds it, so caching positives keeps steady-state
     * inserts query-free while still tolerating the pre-backfill migration window.
     *
     * @var array<string, true>
     */
    protected static array $tablesWithPublicId = [];

    public static function bootHasPublicId(): void
    {
        static::creating(function ($model): void {
            if (! empty($model->public_id)) {
                return;
            }

            if (! static::tableHasPublicIdColumn($model)) {
                return;
            }

            $model->public_id = strtolower((string) Str::ulid());
        });

        // A replicated model must not inherit its source's identity; clearing it
        // lets the `creating` hook mint a fresh ULID for the new row.
        static::replicating(function ($model): void {
            $model->public_id = null;
        });
    }

    /**
     * Whether the model's table carries the `public_id` column yet. During a
     * fresh migration, seeders invoked by historical migrations create rows
     * before the column exists; those rows are backfilled later, so the trait
     * simply skips assignment until the column is present.
     */
    protected static function tableHasPublicIdColumn($model): bool
    {
        $table = $model->getTable();

        if (isset(static::$tablesWithPublicId[$table])) {
            return true;
        }

        if (Schema::connection($model->getConnectionName())->hasColumn($table, 'public_id')) {
            static::$tablesWithPublicId[$table] = true;

            return true;
        }

        return false;
    }
}
