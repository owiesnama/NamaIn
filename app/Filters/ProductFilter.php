<?php

namespace App\Filters;

class ProductFilter extends Filters
{
    protected $filters = ['search', 'status', 'category', 'min_cost', 'max_cost', 'expire_from', 'expire_to'];

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

    public function min_cost($min)
    {
        return $this->builder->where('cost', '>=', $min);
    }

    public function max_cost($max)
    {
        return $this->builder->where('cost', '<=', $max);
    }

    public function expire_from($from)
    {
        return $this->builder->whereDate('expire_date', '>=', $from);
    }

    public function expire_to($to)
    {
        return $this->builder->whereDate('expire_date', '<=', $to);
    }
}
