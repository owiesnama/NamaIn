<?php

namespace App\Filters\Reports;

use App\Filters\Filters;

class InventoryValuationFilter extends Filters
{
    protected $filters = [
        'storage',
        'category',
    ];

    public function storage($id)
    {
        return $this->builder->where('stocks.storage_id', $id);
    }

    public function category($id)
    {
        return $this->builder->whereRelation('categories', 'categories.id', $id);
    }
}
