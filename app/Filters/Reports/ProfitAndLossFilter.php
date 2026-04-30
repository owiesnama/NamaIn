<?php

namespace App\Filters\Reports;

use App\Filters\Filters;

class ProfitAndLossFilter extends Filters
{
    protected $filters = [
        'from_date',
        'to_date',
        'preset',
    ];

    public function from_date($date)
    {
        return $this->builder;
    }

    public function to_date($date)
    {
        return $this->builder;
    }

    public function preset($value)
    {
        return $this->builder;
    }
}
