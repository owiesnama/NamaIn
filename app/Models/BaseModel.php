<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model
{
    protected $guarded = [];

    protected $searchableRelationsAttributes = [];

    public function scopeSearch($query, $searchTerm = '')
    {
        $columns = Schema::getColumnListing($this->getTable());
        $columns = array_merge($columns, $this->searchableRelationsAttributes);

        return $query->when($searchTerm, fn ($query) => $query->where(fn ($query) => $query->whereLike($columns, $searchTerm)));
    }

    public function getCreatedAtAttribute()
    {
        return Carbon::parse($this->attributes['created_at'])->diffForHumans();
    }
}
