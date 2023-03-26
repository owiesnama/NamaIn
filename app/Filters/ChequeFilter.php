<?php

namespace App\Filters;

use App\Enums\ChequeStatus;
use Carbon\Carbon;

class ChequeFilter extends Filter
{
    protected $filters = [
        'status', 'type', 'due',
    ];

    public function type($type)
    {
        return $this->builder->where(fn ($query) => $query->whereNotNull('type')->where('type', $type));
    }

    public function status($status)
    {
        $status = collect($status)->map(fn ($state) => ChequeStatus::tryFrom($state));
        $this->builder->whereIn('status', $status);
    }

    public function due($due)
    {
        return $this->builder->whereDate('due', '<=', Carbon::parse($due));
    }
}
