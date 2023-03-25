<?php

namespace App\Http\Controllers;

use App\Models\Customer;

class CustomersController extends Controller
{
    public function index()
    {
        return inertia('Customers', [
            'customers_count' => Customer::count(),
            'customers' => Customer::search(request('search'))
                ->latest()
                ->paginate(16)
                ->withQueryString(),
        ]);
    }

    public function store()
    {
        Customer::create(
            request()->validate([
                'name' => 'required',
                'phone' => 'required|numeric|min:10',
            ])
        );

        return back()->with('flash', [
            'title' => 'Customer Created 🎉',
            'message' => 'Customer created successfully',
        ]);
    }
}
