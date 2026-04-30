<?php

namespace App\Filters\Reports;

use App\Filters\Filters;

class SalesReportFilter extends Filters
{
    protected $filters = [
        'from_date',
        'to_date',
        'preset',
        'customer',
        'product',
        'payment_method',
        'channel',
        'group_by',
    ];

    public function from_date($date)
    {
        return $this->builder->whereDate('invoices.created_at', '>=', $date);
    }

    public function to_date($date)
    {
        return $this->builder->whereDate('invoices.created_at', '<=', $date);
    }

    public function customer($id)
    {
        return $this->builder->where('invoices.invocable_id', $id);
    }

    public function product($id)
    {
        return $this->builder->where('transactions.product_id', $id);
    }

    public function payment_method($method)
    {
        return $this->builder->where('invoices.payment_method', $method);
    }

    public function channel($channel)
    {
        return match ($channel) {
            'pos' => $this->builder->whereNotNull('invoices.pos_session_id'),
            'b2b' => $this->builder->whereNull('invoices.pos_session_id'),
            default => $this->builder,
        };
    }

    public function group_by($value)
    {
        return $this->builder;
    }

    public function preset($value)
    {
        return $this->builder;
    }
}
