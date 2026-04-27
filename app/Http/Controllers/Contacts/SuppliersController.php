<?php

namespace App\Http\Controllers\Contacts;

use App\Actions\SyncCategoriesAction;
use App\Filters\SupplierFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierRequest;
use App\Models\Category;
use App\Models\Supplier;

class SuppliersController extends Controller
{
    public function index(SupplierFilter $filter)
    {
        $this->authorize('viewAny', Supplier::class);

        return inertia('Suppliers/Index', [
            'suppliers' => Supplier::filter($filter)
                ->when(request('sort_by'), function ($query, $sortBy) {
                    $query->orderBy(in_array($sortBy, ['name', 'created_at']) ? $sortBy : 'name', request('sort_order', 'asc'));
                }, function ($query) {
                    $query->orderBy('name', 'asc');
                })
                ->with('categories')
                ->withCount(['invoices', 'payments'])
                ->withSum('invoices as total_invoiced', \DB::raw('total - discount'))
                ->withMax('invoices as last_transaction_date', 'created_at')
                ->get()
                ->map(fn ($supplier) => [
                    ...$supplier->toArray(),
                    'account_balance' => (float) $supplier->account_balance,
                    'total_invoiced' => (float) $supplier->total_invoiced,
                    'last_transaction_date' => $supplier->last_transaction_date,
                ]),
            'categories' => Category::ofType('supplier')->get(),
        ]);
    }

    public function store(SupplierRequest $request, SyncCategoriesAction $syncCategories)
    {
        $this->authorize('create', Supplier::class);

        $supplier = Supplier::create($request->safe()->except('categories'));

        $syncCategories->handle($supplier, $request->get('categories', []));

        if ($request->wantsJson() || $request->hasHeader('X-Quick-Add')) {
            return response()->json([
                'data' => $supplier,
                'message' => __('Supplier created successfully'),
            ]);
        }

        return redirect()->route('suppliers.index')
            ->with('success', __('Supplier created successfully'));
    }

    public function update(Supplier $supplier, SupplierRequest $request, SyncCategoriesAction $syncCategories)
    {
        $this->authorize('update', $supplier);

        $supplier->update($request->safe()->except('categories'));

        $syncCategories->handle($supplier, $request->get('categories', []));

        return back()->with('success', __('Supplier updated successfully'));
    }

    public function destroy(Supplier $supplier)
    {
        $this->authorize('delete', $supplier);

        $supplier->delete();

        return back()->with('success', __('Supplier deleted successfully'));
    }
}
