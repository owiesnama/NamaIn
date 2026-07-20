<?php

namespace App\Filters;

use App\ValueObjects\Money;

class ProductFilter extends Filters
{
    protected $filters = ['search', 'status', 'type', 'category', 'min_cost', 'max_cost', 'expire_from', 'expire_to'];

    public function search($searchTerm)
    {
        return $this->builder->search($searchTerm);
    }

    public function type($type)
    {
        return $this->builder->where('type', $type);
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
        return $this->builder->where('cost', '>=', Money::fromMajor($min)->minor());
    }

    public function max_cost($max)
    {
        return $this->builder->where('cost', '<=', Money::fromMajor($max)->minor());
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
