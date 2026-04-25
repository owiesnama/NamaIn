<?php

namespace App\Filters;

class PaymentFilter extends Filters
{
    protected $filters = ['search', 'status', 'direction', 'method', 'date_from', 'date_to', 'party_type'];

    public function search($searchTerm)
    {
        return $this->builder->search($searchTerm);
    }

    public function status($status)
    {
        return $this->builder->trash($status);
    }

    public function direction($value)
    {
        return $this->builder->where('direction', $value);
    }

    public function method($value)
    {
        return $this->builder->where('payment_method', $value);
    }

    public function date_from($value)
    {
        return $this->builder->where('paid_at', '>=', $value);
    }

    public function date_to($value)
    {
        return $this->builder->where('paid_at', '<=', $value.' 23:59:59');
    }

    public function party_type($value)
    {
        return $this->builder->where('payable_type', 'like', "%{$value}%");
    }
}
