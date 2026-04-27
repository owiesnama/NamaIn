<?php

namespace App\Http\Controllers\Contacts;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Queries\StatementQuery;
use App\Services\StatementService;
use App\Traits\HandlesPartyAccount;

class CustomerStatementController extends Controller
{
    use HandlesPartyAccount;

    protected string $inertiaFolder = 'Customers';

    public function show(Customer $customer, StatementQuery $query)
    {
        return $this->handleStatement($customer, $query);
    }

    public function store(Customer $customer, StatementService $statementService)
    {
        return $this->handlePrintStatement($customer, $statementService);
    }
}
