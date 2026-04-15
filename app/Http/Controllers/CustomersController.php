<?php

namespace App\Http\Controllers;

use App\Actions\SyncCategoriesAction;
use App\Exports\CustomerExport;
use App\Filters\CustomerFilter;
use App\Http\Controllers\Traits\HasPartyFeatures;
use App\Http\Requests\CustomerRequest;
use App\Imports\CustomerImport;
use App\Models\Category;
use App\Models\Customer;
use App\Queries\PartyAccountQuery;
use App\Queries\StatementQuery;
use App\Services\StatementService;

class CustomersController extends Controller
{
    use HasPartyFeatures;

    protected string $model = Customer::class;

    protected string $inertiaFolder = 'Customers';

    protected string $importClass = CustomerImport::class;

    protected string $exportClass = CustomerExport::class;

    protected array $importHeaders = ['name', 'address', 'phone_number', 'credit_limit', 'opening_balance'];

    protected array $importSampleData = ['Example Customer', 'Customer Address 123', '0123456789', '5000', '1000'];

    public function index(CustomerFilter $filter)
    {
        return inertia('Customers/Index', [
            'customers' => Customer::filter($filter)
                ->when(request('sort_by'), function ($query, $sortBy) {
                    $query->orderBy(in_array($sortBy, ['name', 'created_at', 'credit_limit']) ? $sortBy : 'name', request('sort_order', 'asc'));
                }, function ($query) {
                    $query->orderBy('name', 'asc');
                })
                ->with('categories')
                ->withCount(['invoices', 'payments'])
                ->get()
                ->map(fn ($customer) => [
                    ...$customer->toArray(),
                    'account_balance' => (float) $customer->calculateAccountBalance(),
                    'total_invoiced' => (float) $customer->invoices()->sum(\DB::raw('total - discount')),
                    'last_transaction_date' => $customer->invoices()->latest()->value('created_at'),
                ]),
            'categories' => Category::ofType('customer')->get(),
        ]);
    }

    public function store(CustomerRequest $request, SyncCategoriesAction $syncCategories)
    {
        $customer = Customer::create($request->safe()->except('categories'));

        $syncCategories->execute($customer, $request->get('categories', []));

        if ($request->wantsJson() || $request->hasHeader('X-Quick-Add')) {
            return response()->json([
                'data' => $customer,
                'message' => 'Customer Created Successfully',
            ]);
        }

        return redirect()->route('customers.index')
            ->with('success', 'Customer Created Successfully');
    }

    public function update(Customer $customer, CustomerRequest $request, SyncCategoriesAction $syncCategories)
    {
        $customer->update($request->safe()->except('categories'));

        $syncCategories->execute($customer, $request->get('categories', []));

        return back()->with('success', 'customer updated successfully');
    }

    public function destroy(Customer $customer)
    {
        $this->authorize('delete', $customer);
        $customer->delete();

        return back()->with('success', 'Customer Deleted successfully');
    }

    public function account(Customer $customer, PartyAccountQuery $query)
    {
        return $this->handleAccount($customer, $query);
    }

    public function statement(Customer $customer, StatementQuery $query)
    {
        return $this->handleStatement($customer, $query);
    }

    public function printStatement(Customer $customer, StatementService $statementService)
    {
        return $this->handlePrintStatement($customer, $statementService);
    }
}
