<?php

namespace App\Http\Controllers\Contacts;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Queries\PartyAccountQuery;
use App\Traits\HandlesPartyAccount;

class CustomerAccountController extends Controller
{
    use HandlesPartyAccount;

    protected string $inertiaFolder = 'Customers';

    public function show(Customer $customer, PartyAccountQuery $query)
    {
        $this->authorize('view', $customer);

        return $this->handleAccount($customer, $query);
    }
}
