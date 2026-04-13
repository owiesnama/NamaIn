<?php

namespace App\Filters;

class StorageFilter extends Filters
{
    protected $filters = ['search', 'status'];

    public function search($searchTerm)
    {
        return $this->builder->search($searchTerm);
    }

    public function status($status)
    {
        return $this->builder->trash($status);
    }
}
