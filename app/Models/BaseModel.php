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
        $columns = array_merge($columns, $this->searchableRelationsAttributes);

        return $query->when($searchTerm,
            fn ($query) => $query->where(fn ($query) => $query->whereLike($columns, $searchTerm))
        );
    }

    /**
     * Get created attribute formatted in a readable way.
     */
    public function getCreatedAtAttribute(): string
    {
        return Carbon::parse($this->attributes['created_at'])->diffForHumans();
    }
}
