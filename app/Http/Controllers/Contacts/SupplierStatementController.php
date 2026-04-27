<?php

namespace App\Http\Controllers\Contacts;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Queries\StatementQuery;
use App\Services\StatementService;
use App\Traits\HandlesPartyAccount;

class SupplierStatementController extends Controller
{
    use HandlesPartyAccount;

    protected string $inertiaFolder = 'Suppliers';

    public function show(Supplier $supplier, StatementQuery $query)
    {
        return $this->handleStatement($supplier, $query);
    }

    public function store(Supplier $supplier, StatementService $statementService)
    {
        return $this->handlePrintStatement($supplier, $statementService);
    }
}
