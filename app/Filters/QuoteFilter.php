<?php

namespace App\Filters;

class QuoteFilter extends Filters
{
    protected $filters = ['search', 'status', 'customer_id', 'date_from', 'date_to'];

    public function search(string $term): void
    {
        $this->builder->search($term);
    }

    public function status(string $status): void
    {
        $this->builder->where('status', $status);
    }

    public function customer_id(int $id): void
    {
        $this->builder->where('customer_id', $id);
    }

    public function date_from(string $date): void
    {
        $this->builder->whereDate('created_at', '>=', $date);
    }

    public function date_to(string $date): void
    {
        $this->builder->whereDate('created_at', '<=', $date);
    }
}
