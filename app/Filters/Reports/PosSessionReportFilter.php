<?php

namespace App\Filters\Reports;

use App\Filters\Filters;

class PosSessionReportFilter extends Filters
{
    protected $filters = [
        'from_date',
        'to_date',
        'preset',
        'operator',
    ];

    public function from_date($date)
    {
        return $this->builder->whereDate('pos_sessions.created_at', '>=', $date);
    }

    public function to_date($date)
    {
        return $this->builder->whereDate('pos_sessions.created_at', '<=', $date);
    }

    public function operator($userId)
    {
        return $this->builder->where('pos_sessions.opened_by', $userId);
    }

    public function preset($value)
    {
        return $this->builder;
    }
}
