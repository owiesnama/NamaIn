<?php

namespace App\Http\Controllers;

use App\Models\Customer;

class CustomersController extends Controller
{
    public function index()
    {
        return inertia('Customers', [
            'customers' => Customer::search(request('search'))
                ->latest()
                ->paginate(parent::ELEMENTS_PER_PAGE)
                ->withQueryString(),
        ]);
    }

    public function store()
    {
        $data = request()->validate([
            'name' => 'required',
            'address' => 'required|string|min:10',
            'phone_number' => 'required|numeric|min:10',
        ]);

        Customer::create($data);

        return redirect()->route('customers.index')
            ->with('success', 'Customer Created Successfully');
    }
}
