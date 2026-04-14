<?php

namespace App\Filters;

class InvoiceFilter extends Filters
{
    protected $filters = ['search', 'status', 'product_id'];

    public function product_id($id)
    {
        return $this->builder->whereHas('transactions', fn ($q) => $q->where('product_id', $id));
    }

    public function search($searchTerm)
    {
        return $this->builder->search($searchTerm);
    }

    public function status($status)
    {
        return $this->builder->trash($status);
    }
}
