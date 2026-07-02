<?php

namespace App\Queries\Reports;

use App\Models\Customer;

class CustomerAgingQuery extends PartyAgingQuery
{
    protected function partyTable(): string
    {
        return 'customers';
    }

    protected function partyMorphClass(): string
    {
        return Customer::class;
    }

    protected function partyKey(): string
    {
        return 'customer';
    }
}
