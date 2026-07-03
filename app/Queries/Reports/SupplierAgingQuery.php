<?php

namespace App\Queries\Reports;

use App\Models\Supplier;

class SupplierAgingQuery extends PartyAgingQuery
{
    protected function partyTable(): string
    {
        return 'suppliers';
    }

    protected function partyMorphClass(): string
    {
        return Supplier::class;
    }

    protected function partyKey(): string
    {
        return 'supplier';
    }
}
