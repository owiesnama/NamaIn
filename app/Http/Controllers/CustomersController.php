<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Models\Customer;

class CustomersController extends Controller
{
    public function index()
    {
        return inertia('Customers/Index', [
            'customers' => Customer::search(request()->get('search'))
                ->trash(request()->get('trashStatus'))
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

    /**
     * Show customer account with balance and unpaid invoices.
     */
    public function account(Customer $customer)
    {
        return inertia('Customers/Account', [
            'customer' => $customer,
            'account_balance' => $customer->account_balance,
            'unpaid_invoices' => $customer->getUnpaidInvoices(),
            'payment_history' => $customer->getPaymentHistory(),
        ]);
    }

    /**
     * Get customer statement for a date range.
     */
    public function statement(Customer $customer)
    {
        $startDate = request('start_date', now()->subMonth());
        $endDate = request('end_date', now());

        $invoices = $customer->invoices()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('payments')
            ->get();

        $payments = $customer->getPaymentHistory()
            ->whereBetween('paid_at', [$startDate, $endDate]);

        return inertia('Customers/Statement', [
            'customer' => $customer,
            'invoices' => $invoices,
            'payments' => $payments,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'opening_balance' => $customer->calculateAccountBalance(),
        ]);
    }
}
