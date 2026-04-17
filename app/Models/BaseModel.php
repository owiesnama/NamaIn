<?php

namespace App\Models;

use App\Filters\Filters;
use App\Traits\Searchable;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{
    use HasFactory,Searchable;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function booted(): void
    {
        static::unguard();
    }

    /**
     * Scope for filtering the query with a given filter.
     */
    public function scopeFilter(Builder $query, Filters $filter): Builder
    {
        return $filter->apply($query);
    }

    /**
     * Get created_at formatted in a readable way.
     */
    public function getCreatedAtHumanAttribute(): string
    {
        return $this->created_at?->locale(app()->getLocale() ?: 'en')->diffForHumans() ?? '';
    }
}
