<?php

namespace App\Filters\Reports;

use App\Filters\Filters;

class CustomerAgingFilter extends Filters
{
    protected $filters = [
        'customer',
    ];

    public function customer($id)
    {
        return $this->builder->where('invoices.invocable_id', $id);
    }
}
