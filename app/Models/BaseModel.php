<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

abstract class BaseModel extends Model
{
    /**
     * List of searchable model's relation attributes
     *
     * @var array<string>
     */
    protected array $searchableRelationsAttributes = [];

    /**
     * Scope for searching the attribute of the model.
     */
    public function scopeSearch($query, $searchTerm = ''): Builder
    {
        $columns = Schema::getColumnListing($this->getTable());
        // Exclude internal/technical columns that don't make sense to search by text
        $columns = array_diff($columns, ['id', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'invocable_id', 'invocable_type']);
        $columns = array_merge($columns, $this->searchableRelationsAttributes);
        $like = config('database.default') === 'pgsql' ? 'ILIKE' : 'LIKE';

        return $query->when($searchTerm,
            fn ($query) => $query->where(function ($query) use ($searchTerm, $columns, $like) {
                foreach ($columns as $column) {
                    if (str_contains($column, '.')) {
                        $parts = explode('.', $column);
                        $attribute = array_pop($parts);
                        $relation = implode('.', $parts);

                        $query->orWhereHas($relation, function ($query) use ($attribute, $searchTerm, $like) {
                            $query->where($attribute, $like, "%{$searchTerm}%");
                        });
                    } else {
                        $query->orWhere($this->getTable().'.'.$column, $like, "%{$searchTerm}%");
                    }
                }
            })
        );
    }

    /**
     * Get created attribute formatted in a readable way.
     */
    public function getCreatedAtAttribute($value): string
    {
        return Carbon::parse($value)->locale(app()->getLocale())->diffForHumans();
    }
}
