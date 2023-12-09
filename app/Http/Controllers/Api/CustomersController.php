<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;

class CustomersController extends Controller
{
    public function __invoke()
    {
        return Customer::search(request()->get('search'))
            ->latest()->limit(5)->get();
    }
}
