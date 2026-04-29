<?php

namespace App\Filters\Reports;

use App\Filters\Filters;

class PurchaseReportFilter extends Filters
{
    protected $filters = [
        'from_date',
        'to_date',
        'preset',
        'supplier',
        'product',
    ];

    public function from_date($date)
    {
        return $this->builder->whereDate('invoices.created_at', '>=', $date);
    }

    public function to_date($date)
    {
        return $this->builder->whereDate('invoices.created_at', '<=', $date);
    }

    public function supplier($id)
    {
        return $this->builder->where('invoices.invocable_id', $id);
    }

    public function product($id)
    {
        return $this->builder->where('transactions.product_id', $id);
    }

    public function preset($value)
    {
        return $this->builder;
    }
}
