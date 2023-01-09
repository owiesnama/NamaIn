<?php

namespace App\Http\Controllers;

use App\Models\Customer;

class CustomersController extends Controller
{
    public function index()
    {
        return inertia('Customers', [
            'customers' => Customer::search(request('search'))->latest()->get(),
        ]);
    }

    public function store()
    {
        $attributes = request()->validate([
            'name' => 'required',
            'phone' => 'required|numeric|min:10',
        ]);
        
        $customer = Customer::create([
            'name' => $attributes['name'],
            'phone_number' => $attributes['phone'],
        ]);

        return inertia('Customers')->with('success','Customer has been added successfully');
    }
}
