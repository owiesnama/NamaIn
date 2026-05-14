<?php

namespace App\Http\Controllers\Contacts;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Queries\StatementQuery;
use App\Traits\HandlesPartyAccount;

class CustomerStatementController extends Controller
{
    use HandlesPartyAccount;

    protected string $inertiaFolder = 'Customers';

    public function show(Customer $customer, StatementQuery $query)
    {
        $this->authorize('view', $customer);

        return $this->handleStatement($customer, $query);
    }

    public function store(Customer $customer)
    {
        $this->authorize('view', $customer);

        return $this->handlePrintStatement($customer, 'customers.statement');
    }
}
