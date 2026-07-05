<?php

namespace App\Services\Sync;

use App\Http\Middleware\Sync\RecordSyncLog;

/**
 * A request-scoped collector for sync audit fields (Design 02 §8.1). Endpoints
 * push their own fields (cursor window, mutation counts, client_pushed_at) here
 * and {@see RecordSyncLog} reads them when it writes
 * the row — a container singleton, so it is shared across the middleware and a
 * FormRequest-injected controller regardless of Request instance identity.
 */
class SyncLogContext
{
    /** @var array<string, mixed> */
    private array $fields = [];

    /**
     * @param  array<string, mixed>  $fields
     */
    public function set(array $fields): void
    {
        $this->fields = array_merge($this->fields, $fields);
    }

    /**
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    public function reset(): void
    {
        $this->fields = [];
    }
}
