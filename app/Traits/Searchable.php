<?php

namespace App\Traits;

use Illuminate\Contracts\Database\Eloquent\Builder;

trait Searchable
{
    /**
     * Direct columns to search against.
     *
     * @var array<string>
     */
    protected array $searchable = [];

    /**
     * List of searchable model's relation attributes
     *
     * @var array<string>
     */
    protected array $searchableRelationsAttributes = [];

    /**
     * Scope for searching the attribute of the model.
     */
    public function scopeSearch(Builder $query, ?string $searchTerm = ''): Builder
    {
        if (! $searchTerm) {
            return $query;
        }

        $searchable = array_merge($this->searchable, $this->searchableRelationsAttributes);
        $operator = $this->getLikeOperator();

        return $query->where(function (Builder $query) use ($searchTerm, $searchable, $operator) {
            foreach ($searchable as $column) {
                $this->applySearchCondition($query, $column, $searchTerm, $operator);
            }
        });
    }

    /**
     * Apply the search condition for a single column.
     */
    protected function applySearchCondition(Builder $query, string $column, string $searchTerm, string $operator): void
    {
        if (str_contains($column, '.')) {
            [$relation, $attribute] = $this->parseSearchableColumn($column);

            $query->orWhereHas($relation, function (Builder $query) use ($attribute, $searchTerm, $operator) {
                if (str_contains($attribute, '.')) {
                    $this->applyNestedSearchCondition($query, $attribute, $searchTerm, $operator);

                    return;
                }

                $query->where($query->getModel()->getTable().'.'.$attribute, $operator, "%{$searchTerm}%");
            });

            return;
        }

        $query->orWhere($this->getTable().'.'.$column, $operator, "%{$searchTerm}%");
    }

    /**
     * Apply the search condition for a nested relation.
     */
    protected function applyNestedSearchCondition(Builder $query, string $column, string $searchTerm, string $operator): void
    {
        [$relation, $attribute] = $this->parseSearchableColumn($column);

        $query->whereHas($relation, function (Builder $query) use ($attribute, $searchTerm, $operator) {
            if (str_contains($attribute, '.')) {
                $this->applyNestedSearchCondition($query, $attribute, $searchTerm, $operator);

                return;
            }

            $query->where($query->getModel()->getTable().'.'.$attribute, $operator, "%{$searchTerm}%");
        });
    }

    /**
     * Get the LIKE operator based on the database driver.
     */
    protected function getLikeOperator(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
    }

    /**
     * Parse the searchable column into relation and attribute.
     *
     * @return array{0: string, 1: string}
     */
    protected function parseSearchableColumn(string $column): array
    {
        $parts = explode('.', $column);
        $attribute = array_pop($parts);
        $relation = implode('.', $parts);

        return [$relation, $attribute];
    }
}
