<?php

namespace App\Http\Controllers\Contacts;

use App\Actions\SyncCategoriesAction;
use App\Exports\CustomerExport;
use App\Filters\CustomerFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRequest;
use App\Imports\CustomerImport;
use App\Models\Category;
use App\Models\Customer;
use App\Queries\PartyAccountQuery;
use App\Queries\StatementQuery;
use App\Services\StatementService;
use App\Traits\HandlesPartyAccount;
use App\Traits\HandlesPartyImportExport;

class CustomersController extends Controller
{
    use HandlesPartyAccount, HandlesPartyImportExport;

    protected string $model = Customer::class;

    protected string $inertiaFolder = 'Customers';

    protected string $importClass = CustomerImport::class;

    protected string $exportClass = CustomerExport::class;

    protected array $importHeaders = ['name', 'address', 'phone_number', 'credit_limit', 'opening_balance'];

    protected array $importSampleData = ['Example Customer', 'Customer Address 123', '0123456789', '5000', '1000'];

    public function index(CustomerFilter $filter)
    {
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
