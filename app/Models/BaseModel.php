<?php

namespace App\Models;

use App\Filters\Filters;
use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
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
     * Scope for filtering the query with a given filter.
     */
    public function scopeFilter(Builder $query, Filters $filter): Builder
    {
        return $filter->apply($query);
    }

    /**
     * Scope for searching the attribute of the model.
     */
    public function scopeSearch($query, $searchTerm = ''): Builder
    {
        $columns = array_merge($this->searchable, $this->searchableRelationsAttributes);
        $like = config('database.default') === 'pgsql' ? 'ILIKE' : 'LIKE';

        return $query->when($searchTerm,
            fn ($query) => $query->where(function ($query) use ($searchTerm, $columns, $like) {
                foreach ($columns as $column) {
                    if (str_contains($column, '.')) {
                        $parts = explode('.', $column);
                        $attribute = array_pop($parts);
                        $relation = implode('.', $parts);

                        $query->orWhereHas($relation, function ($query) use ($attribute, $searchTerm, $like) {
                            $model = $query->getModel();
                            $query->where($model->getTable().'.'.$attribute, $like, "%{$searchTerm}%");
                        });
                    } else {
                        $query->orWhere($this->getTable().'.'.$column, $like, "%{$searchTerm}%");
                    }
                }
            })
        );
    }

    /**
     * Get created_at formatted in a readable way.
     */
    public function getCreatedAtAttribute($value): string
    {
        return Carbon::parse($value)->locale(app()->getLocale() ?: 'en')->diffForHumans();
    }
}
