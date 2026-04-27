<?php

namespace App\Http\Controllers\Inventory;

use App\Filters\StorageFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorageRequest;
use App\Models\Storage;
use App\Queries\StorageShowQuery;

class StoragesController extends Controller
{
    public function index(StorageFilter $filter)
    {
        return inertia('Storages/Index', [
            'storages_count' => Storage::count(),
            'storages' => Storage::filter($filter)
                ->when(request('sort_by'), function ($query, $sortBy) {
                    $query->orderBy(in_array($sortBy, ['name', 'created_at']) ? $sortBy : 'name', request('sort_order', 'asc'));
                }, function ($query) {
                    $query->orderBy('name', 'asc');
                })
                ->withCount('stock')
                ->with('stock')
                ->withMax('transactions', 'created_at')
                ->get()
                ->map(function ($storage) {
                    $totalStockValue = $storage->stock->sum(
                        fn ($product) => $product->pivot->quantity * $product->average_cost
                    );
                    $totalQuantity = $storage->stock->sum(
                        fn ($product) => $product->pivot->quantity
                    );

                    return [
                        ...$storage->toArray(),
                        'total_stock_value' => (float) $totalStockValue,
                        'total_quantity' => (float) $totalQuantity,
                        'last_movement_date' => $storage->transactions_max_created_at,
                    ];
                }),
        ]);
    }

    public function show(Storage $storage, StorageShowQuery $query)
    {
        $storage->load('stock');

        $filters = request()->only(['from_date', 'to_date', 'product_id', 'type', 'search']);
        $query->forStorage($storage);

        return inertia('Storages/Show', [
            'storage' => $storage,
            'products' => $storage->stock()
                ->when(request('search'), function ($q, $search) {
                    $q->where('name', config('database.default') === 'pgsql' ? 'ILIKE' : 'LIKE', "%{$search}%");
                })
                ->paginate(parent::ELEMENTS_PER_PAGE)
                ->withQueryString(),
            'transactions' => $query->transactions($filters),
            'all_products' => $storage->stock()->select('products.id', 'products.name')->get(),
            'filters' => $filters,
            'stats' => [
                'sales_count' => $query->salesCount($filters),
                'purchases_count' => $query->purchasesCount($filters),
                'total_stock_value' => $query->totalStockValue(),
                'unique_products_count' => $storage->stock()->count(),
            ],
            'chart_data' => $query->chartData(),
        ]);
    }

    public function store(StorageRequest $request)
    {
        Storage::create($request->validated());

        return back()->with('success', __('Storage created successfully'));
    }

    public function update(Storage $storage, StorageRequest $request)
    {
        $storage->update($request->validated());

        return back()->with('success', __('Storage updated successfully'));
    }

    public function destroy(Storage $storage)
    {
        $this->authorize('delete', $storage);

        $storage->delete();

        return back()->with('success', __('Storage deleted successfully'));
    }
}
