<?php

namespace App\Services\Sync;

use Illuminate\Database\Eloquent\Model;

/**
 * The public_id <-> int id boundary (Design 02 §7). Int primary keys never
 * cross the wire; every FK is projected outbound as the referenced row's
 * public_id, and resolved inbound within the bound tenant scope.
 */
class PublicIdResolver
{
    /**
     * Inbound: resolve a wire public_id to the local int id. Runs on the
     * model's default (tenant-bound) query so unknown or cross-tenant
     * references fail closed to null.
     *
     * @param  class-string<Model>  $modelClass
     */
    public function id(string $modelClass, ?string $publicId): ?int
    {
        if ($publicId === null) {
            return null;
        }

        $id = $modelClass::query()->where('public_id', $publicId)->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Inbound morph: resolve a wire (type token, public_id) pair.
     *
     * @return array{0: class-string<Model>, 1: int}|null
     */
    public function morph(string $typeToken, ?string $publicId): ?array
    {
        $modelClass = SyncMorphMap::modelClass($typeToken);

        if ($modelClass === null) {
            return null;
        }

        $id = $this->id($modelClass, $publicId);

        return $id === null ? null : [$modelClass, $id];
    }

    /**
     * Outbound batch: map int ids to public_ids without N+1. Queried without
     * global scopes on purpose — the ids come from FK columns of rows that
     * already passed the tenant scope, and referenced rows may be soft-deleted
     * or global (users, permissions); public_ids themselves are globally
     * unique per table so no cross-tenant information is exposed.
     *
     * @param  class-string<Model>  $modelClass
     * @param  array<int, int|string|null>  $ids
     * @return array<int, string> id => public_id
     */
    public function publicIds(string $modelClass, array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));

        if ($ids === []) {
            return [];
        }

        return $modelClass::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $ids)
            ->pluck('public_id', 'id')
            ->all();
    }
}
