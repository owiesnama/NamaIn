<?php

namespace App\Filters;

use App\Models\Customer;

class PosInvoiceFilter extends Filters
{
    protected $filters = ['search', 'session_id', 'from_date', 'to_date', 'customer_type'];

    public function search(string $searchTerm)
    {
        return $this->builder->where(function ($query) use ($searchTerm) {
            $query->where('serial_number', 'like', "%{$searchTerm}%")
                ->orWhereHasMorph('invocable', [Customer::class], function ($customerQuery) use ($searchTerm) {
                    $customerQuery->where('name', 'like', "%{$searchTerm}%");
                });
        });
    }

    public function session_id(int $sessionId)
    {
        return $this->builder->where('pos_session_id', $sessionId);
    }

    public function from_date(string $fromDate)
    {
        return $this->builder->whereDate('created_at', '>=', $fromDate);
    }

    public function to_date(string $toDate)
    {
        return $this->builder->whereDate('created_at', '<=', $toDate);
    }

    public function customer_type(string $customerType)
    {
        if ($customerType === 'walk_in') {
            return $this->builder->whereHasMorph('invocable', [Customer::class], function ($query) {
                $query->where('is_system', true);
            });
        }

        if ($customerType === 'named') {
            return $this->builder->whereHasMorph('invocable', [Customer::class], function ($query) {
                $query->where('is_system', false);
            });
        }

        return $this->builder;
    }
}
