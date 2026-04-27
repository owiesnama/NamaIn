<?php

namespace App\Http\Controllers\Contacts;

use App\Actions\SyncCategoriesAction;
use App\Filters\CustomerFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRequest;
use App\Models\Category;
use App\Models\Customer;

class CustomersController extends Controller
{
    public function index(CustomerFilter $filter)
    {
        $this->authorize('viewAny', Customer::class);

        return inertia('Customers/Index', [
            'customers' => Customer::query()
                ->where('is_system', false)
                ->filter($filter)
                ->when(request('sort_by'), function ($query, $sortBy) {
                    $query->orderBy(in_array($sortBy, ['name', 'created_at', 'credit_limit']) ? $sortBy : 'name', request('sort_order', 'asc'));
                }, function ($query) {
                    $query->orderBy('name', 'asc');
                })
                ->with('categories')
                ->withCount(['invoices', 'payments'])
                ->withSum('invoices as total_invoiced', \DB::raw('total - discount'))
                ->withMax('invoices as last_transaction_date', 'created_at')
                ->get()
                ->map(fn ($customer) => [
                    ...$customer->toArray(),
                    'account_balance' => (float) $customer->account_balance,
                    'total_invoiced' => (float) $customer->total_invoiced,
                    'last_transaction_date' => $customer->last_transaction_date,
                ]),
            'categories' => Category::ofType('customer')->get(),
        ]);
    }

    public function store(CustomerRequest $request, SyncCategoriesAction $syncCategories)
    {
        $this->authorize('create', Customer::class);

        $customer = Customer::create($request->safe()->except('categories'));

        $syncCategories->handle($customer, $request->get('categories', []));

        if ($request->wantsJson() || $request->hasHeader('X-Quick-Add')) {
            return response()->json([
                'data' => $customer,
                'message' => __('Customer created successfully'),
            ]);
        }

        return redirect()->route('customers.index')
            ->with('success', __('Customer created successfully'));
    }

    public function update(Customer $customer, CustomerRequest $request, SyncCategoriesAction $syncCategories)
    {
        $this->authorize('update', $customer);

        $customer->update($request->safe()->except('categories'));

        $syncCategories->handle($customer, $request->get('categories', []));

        return back()->with('success', __('Customer updated successfully'));
    }

    public function destroy(Customer $customer)
    {
        $this->authorize('delete', $customer);
        $customer->delete();

        return back()->with('success', __('Customer deleted successfully'));
    }
}
