<?php

namespace App\Reports;

use App\Models\Customer;
use App\Queries\Reports\CustomerAgingQuery;
use Illuminate\Http\Request;

class CustomerAgingReport implements Report
{
    public function __construct(private CustomerAgingQuery $query) {}

    public function page(): string
    {
        return 'Reports/CustomerAging';
    }

    public function props(Request $request): array
    {
        $customerId = $request->input('customer') ? (int) $request->input('customer') : null;

        return [
            'data' => $this->query->get($customerId),
            'summary' => $this->query->summary($customerId),
            'filters' => $request->only(['customer']),
            'customers' => fn () => Customer::pluck('name', 'id'),
        ];
    }
}
