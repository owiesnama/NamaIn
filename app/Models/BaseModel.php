<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class BaseModel extends Model
{
    protected $guarded = [];
    protected $searchableRelationsAttributes = [];
    
    public function scopeSearch($query, $searchTerm = '')
    {
        $columns = Schema::getColumnListing($this->getTable());
        $columns = array_merge($columns, $this->searchableRelationsAttributes);
        return $query->whereLike($columns, $searchTerm);
    }
}
