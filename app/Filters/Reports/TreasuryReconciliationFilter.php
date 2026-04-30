<?php

namespace App\Filters\Reports;

use App\Filters\Filters;

class TreasuryReconciliationFilter extends Filters
{
    protected $filters = [
        'from_date',
        'to_date',
        'preset',
        'treasury_account',
        'reason',
    ];

    public function from_date($date)
    {
        return $this->builder->whereDate('treasury_movements.occurred_at', '>=', $date);
    }

    public function to_date($date)
    {
        return $this->builder->whereDate('treasury_movements.occurred_at', '<=', $date);
    }

    public function treasury_account($id)
    {
        return $this->builder->where('treasury_movements.treasury_account_id', $id);
    }

    public function reason($reason)
    {
        return $this->builder->where('treasury_movements.reason', $reason);
    }

    public function preset($value)
    {
        return $this->builder;
    }
}
