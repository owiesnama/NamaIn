<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Models\Customer;

class CustomersController extends Controller
{
    public function index()
    {
        return inertia('Customers/Index', [
            'customers' => Customer::search(request('search'))
                ->latest()
                ->paginate(parent::ELEMENTS_PER_PAGE)
                ->withQueryString(),
        ]);
    }

    public function store(CustomerRequest $request)
    {
        Customer::create($request->all());

        return redirect()->route('customers.index')
            ->with('success', 'Customer Created Successfully');
    }

    public function update(Customer $customer, CustomerRequest $request)
    {
        $customer->update($request->all());

        return back()->with('success', 'customer updated successfully');
    }

    public function destroy(Customer $customer)
    {
        $this->authorize('delete', $customer);
        $customer->delete();

        return back()->with('success', 'Storage Deleted successfully');
    }
}
