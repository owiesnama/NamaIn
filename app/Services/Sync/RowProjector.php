<?php

namespace App\Services\Sync;

use Illuminate\Support\Collection;

/**
 * Projects raw DB rows onto their wire shape (Design 02 §7 outbound): the
 * declared allow-list only, every FK rewritten to the referenced row's
 * public_id via batched lookups (no N+1), morph types translated to wire
 * tokens, and money passed through as the integer minor units the DB already
 * stores. Rows must come from the base query builder (no Eloquent casts) so
 * MoneyCast never converts to major units.
 */
class RowProjector
{
    public function __construct(private readonly PublicIdResolver $resolver) {}

    /**
     * @param  Collection<int, object>  $rows
     * @return list<array<string, mixed>>
     */
    public function project(SyncEntityDefinition $entity, Collection $rows): array
    {
        $referenceMaps = $this->resolveReferences($entity, $rows);
        $morphMaps = $this->resolveMorphs($entity, $rows);

        return $rows
            ->map(fn (object $row) => $this->projectRow($entity, $row, $referenceMaps, $morphMaps))
            ->all();
    }

    /**
     * @param  array<string, array<int, string>>  $referenceMaps
     * @param  array<string, array<string, string>>  $morphMaps
     * @return array<string, mixed>
     */
    private function projectRow(SyncEntityDefinition $entity, object $row, array $referenceMaps, array $morphMaps): array
    {
        $projected = [];

        if (property_exists($row, 'public_id')) {
            $projected['public_id'] = $row->public_id;
        }

        foreach ($entity->columns as $field => $column) {
            $projected[$field] = $row->{$column};
        }

        foreach ($entity->integers as $field => $column) {
            $projected[$field] = $row->{$column} === null ? null : (int) $row->{$column};
        }

        foreach ($entity->references as $field => [$column, $modelClass]) {
            $projected[$field] = $referenceMaps[$field][$row->{$column}] ?? null;
        }

        foreach ($entity->morphs as $field => [$typeColumn, $idColumn]) {
            $projected["{$field}_type"] = SyncMorphMap::token($row->{$typeColumn});
            $projected[$field] = $morphMaps[$field]["{$row->{$typeColumn}}:{$row->{$idColumn}}"] ?? null;
        }

        foreach ($entity->computed as $field => $compute) {
            $projected[$field] = $compute($row);
        }

        return $projected;
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<string, array<int, string>> field => [id => public_id]
     */
    private function resolveReferences(SyncEntityDefinition $entity, Collection $rows): array
    {
        $maps = [];

        foreach ($entity->references as $field => [$column, $modelClass]) {
            $maps[$field] = $this->resolver->publicIds(
                $modelClass,
                $rows->pluck($column)->all()
            );
        }

        return $maps;
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<string, array<string, string>> field => ["class:id" => public_id]
     */
    private function resolveMorphs(SyncEntityDefinition $entity, Collection $rows): array
    {
        $maps = [];

        foreach ($entity->morphs as $field => [$typeColumn, $idColumn]) {
            $maps[$field] = $rows
                ->filter(fn (object $row) => $row->{$typeColumn} !== null && $row->{$idColumn} !== null)
                ->groupBy($typeColumn)
                ->flatMap(function (Collection $group, string $modelClass) use ($idColumn) {
                    $publicIds = $this->resolver->publicIds($modelClass, $group->pluck($idColumn)->all());

                    return $group->mapWithKeys(fn (object $row) => [
                        "{$modelClass}:{$row->{$idColumn}}" => $publicIds[$row->{$idColumn}] ?? null,
                    ]);
                })
                ->all();
        }

        return $maps;
    }
}
