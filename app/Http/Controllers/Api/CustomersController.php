<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;

class CustomersController extends Controller
{
    public function __invoke()
    {
        return Customer::query()
            ->where('is_system', false)
            ->search(request()->get('search'))
            ->latest()
            ->limit(5)
            ->get();
    }
}
