<?php

namespace App\Filters;

class SupplierFilter extends Filters
{
    protected $filters = ['search', 'status', 'category'];

    public function search($searchTerm)
    {
        return $this->builder->search($searchTerm);
    }

    public function status($status)
    {
        return $this->builder->trash($status);
    }

    public function category($category)
    {
        return $this->builder->whereRelation('categories', 'categories.id', $category);
    }
}
