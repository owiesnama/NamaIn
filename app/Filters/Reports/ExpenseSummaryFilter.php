<?php

namespace App\Filters\Reports;

use App\Filters\Filters;

class ExpenseSummaryFilter extends Filters
{
    protected $filters = [
        'from_date',
        'to_date',
        'preset',
        'category',
        'status',
    ];

    public function from_date($date)
    {
        return $this->builder->whereDate('expensed_at', '>=', $date);
    }

    public function to_date($date)
    {
        return $this->builder->whereDate('expensed_at', '<=', $date);
    }

    public function category($id)
    {
        return $this->builder->whereRelation('categories', 'categories.id', $id);
    }

    public function status($status)
    {
        return $this->builder->where('status', $status);
    }

    public function preset($value)
    {
        return $this->builder;
    }
}
