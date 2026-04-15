<?php

namespace App\Http\Controllers;

use App\Actions\SyncCategoriesAction;
use App\Exports\SupplierExport;
use App\Filters\SupplierFilter;
use App\Http\Controllers\Traits\HasPartyFeatures;
use App\Http\Requests\SupplierRequest;
use App\Imports\SupplierImport;
use App\Models\Category;
use App\Models\Supplier;
use App\Queries\PartyAccountQuery;
use App\Queries\StatementQuery;
use App\Services\StatementService;

class SuppliersController extends Controller
{
    use HasPartyFeatures;

    protected string $model = Supplier::class;

    protected string $inertiaFolder = 'Suppliers';

    protected string $importClass = SupplierImport::class;

    protected string $exportClass = SupplierExport::class;

    protected array $importHeaders = ['name', 'address', 'phone_number', 'opening_balance'];

    protected array $importSampleData = ['Example Supplier', 'Supplier Address 123', '0123456789', '1000'];

    public function index(SupplierFilter $filter)
    {
        return inertia('Suppliers/Index', [
            'suppliers' => Supplier::filter($filter)
                ->when(request('sort_by'), function ($query, $sortBy) {
                    $query->orderBy(in_array($sortBy, ['name', 'created_at']) ? $sortBy : 'name', request('sort_order', 'asc'));
                }, function ($query) {
                    $query->orderBy('name', 'asc');
                })
                ->with('categories')
                ->withCount(['invoices', 'payments'])
                ->get()
                ->map(fn ($supplier) => [
                    ...$supplier->toArray(),
                    'account_balance' => (float) $supplier->calculateAccountBalance(),
                    'total_invoiced' => (float) $supplier->invoices()->sum(\DB::raw('total - discount')),
                    'last_transaction_date' => $supplier->invoices()->latest()->value('created_at'),
                ]),
            'categories' => Category::ofType('supplier')->get(),
        ]);
    }

    public function store(SupplierRequest $request, SyncCategoriesAction $syncCategories)
    {
        $supplier = Supplier::create($request->safe()->except('categories'));

        $syncCategories->execute($supplier, $request->get('categories', []));

        if ($request->wantsJson() || $request->hasHeader('X-Quick-Add')) {
            return response()->json([
                'data' => $supplier,
                'message' => 'Supplier Created Successfully',
            ]);
        }

        return redirect()->route('suppliers.index')
            ->with('success', 'Supplier Created Successfully');
    }

    public function update(Supplier $supplier, SupplierRequest $request, SyncCategoriesAction $syncCategories)
    {
        $supplier->update($request->safe()->except('categories'));

        $syncCategories->execute($supplier, $request->get('categories', []));

        return back()->with('success', 'Supplier updated successfully');
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return back()->with('success', 'Supplier Deleted successfully');
    }

    public function account(Supplier $supplier, PartyAccountQuery $query)
    {
        return $this->handleAccount($supplier, $query);
    }

    public function statement(Supplier $supplier, StatementQuery $query)
    {
        return $this->handleStatement($supplier, $query);
    }

    public function printStatement(Supplier $supplier, StatementService $statementService)
    {
        return $this->handlePrintStatement($supplier, $statementService);
    }
}
