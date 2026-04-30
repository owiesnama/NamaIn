<?php

namespace App\Http\Controllers\Contacts;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Queries\PartyAccountQuery;
use App\Traits\HandlesPartyAccount;

class SupplierAccountController extends Controller
{
    use HandlesPartyAccount;

    protected string $inertiaFolder = 'Suppliers';

    public function show(Supplier $supplier, PartyAccountQuery $query)
    {
        $this->authorize('view', $supplier);

        return $this->handleAccount($supplier, $query);
    }
}
