<?php

namespace App\Filters;

class ExpenseFilter extends Filters
{
    protected $filters = [
        'search',
        'category',
        'status',
        'from_date',
        'to_date',
        'min_amount',
        'max_amount',
        'created_by',
    ];

    public function search($searchTerm)
    {
        return $this->builder->search($searchTerm);
    }

    public function category($id)
    {
        return $this->builder->whereRelation('categories', 'categories.id', $id);
    }

    public function status($status)
    {
        return $this->builder->where('status', $status);
    }

    public function from_date($date)
    {
        return $this->builder->whereDate('expensed_at', '>=', $date);
    }

    public function to_date($date)
    {
        return $this->builder->whereDate('expensed_at', '<=', $date);
    }

    public function min_amount($amount)
    {
        return $this->builder->where('amount', '>=', $amount);
    }

    public function max_amount($amount)
    {
        return $this->builder->where('amount', '<=', $amount);
    }

    public function created_by($userId)
    {
        return $this->builder->where('created_by', $userId);
    }
}
