<?php

namespace App\Filters;

class CustomerFilter extends Filters
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
        return $this->builder->whereHas('categories', function ($query) use ($category) {
            $query->where('categories.id', $category);
        });
    }
}
