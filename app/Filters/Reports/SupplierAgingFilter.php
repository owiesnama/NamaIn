<?php

namespace App\Filters\Reports;

use App\Filters\Filters;

class SupplierAgingFilter extends Filters
{
    protected $filters = [
        'supplier',
    ];

    public function supplier($id)
    {
        return $this->builder->where('invoices.invocable_id', $id);
    }
}
