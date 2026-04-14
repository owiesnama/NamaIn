<?php

namespace App\Http\Controllers;

use App\Filters\StorageFilter;
use App\Http\Requests\StorageRequest;
use App\Models\Customer;
use App\Models\Storage;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

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
                ->get()
                ->map(function ($storage) {
                    $total_stock_value = 0;
                    $total_quantity = 0;

                    $storage->stock()->get()->each(function ($product) use (&$total_stock_value, &$total_quantity) {
                        $total_stock_value += $product->pivot->quantity * $product->average_cost;
                        $total_quantity += $product->pivot->quantity;
                    });

                    return [
                        ...$storage->toArray(),
                        'total_stock_value' => (float) $total_stock_value,
                        'total_quantity' => (float) $total_quantity,
                        'last_movement_date' => $storage->transactions()->latest()->value('created_at'),
                    ];
                }),
        ]);
    }

    public function show(Storage $storage)
    {
        $storage->load('stock');

        $query = Transaction::where('storage_id', $storage->id);

        if (request('from_date')) {
            $query->whereDate('created_at', '>=', request('from_date'));
        }
        if (request('to_date')) {
            $query->whereDate('created_at', '<=', request('to_date'));
        }
        if (request('product_id')) {
            $query->where('product_id', request('product_id'));
        }
        if (request('type') && request('type') !== 'All') {
            if (request('type') === 'Sales') {
                $query->whereHas('invoice', fn ($q) => $q->where('invocable_type', Customer::class));
            } elseif (request('type') === 'Purchases') {
                $query->whereHas('invoice', fn ($q) => $q->where('invocable_type', '!=', Customer::class));
            }
        }

        $transactions = (clone $query)->latest()
            ->with(['invoice.invocable', 'unit', 'product'])
            ->paginate(10)
            ->withQueryString();

        $sales_count = (clone $query)
            ->whereHas('invoice', fn ($q) => $q->where('invocable_type', Customer::class))
            ->sum('base_quantity');

        $purchases_count = (clone $query)
            ->whereHas('invoice', fn ($q) => $q->where('invocable_type', '!=', Customer::class))
            ->sum('base_quantity');

        $total_stock_value = 0;
        $storage->stock()->with(['transactions' => function ($query) {
            $query->where('delivered', true)
                ->whereHas('invoice', fn ($q) => $q->where('invocable_type', '!=', Customer::class));
        }])->get()->each(function ($product) use (&$total_stock_value) {
            $total_stock_value += $product->pivot->quantity * $product->average_cost;
        });

        // Chart Data (Last 6 Months)
        $months = collect();
        for ($i = 5; $i >= 0; $i--) {
            $months->push(Carbon::now()->subMonths($i)->format('Y-m'));
        }

        $chart_sales_query = Transaction::where('storage_id', $storage->id)
            ->whereHas('invoice', fn ($q) => $q->where('invocable_type', Customer::class))
            ->where('created_at', '>=', Carbon::now()->subMonths(5)->startOfMonth());

        if (config('database.default') === 'sqlite') {
            $chart_sales = $chart_sales_query->selectRaw("strftime('%Y-%m', created_at) as month, SUM(quantity) as total")
                ->groupBy('month')
                ->pluck('total', 'month');
        } elseif (config('database.default') === 'pgsql') {
            $chart_sales = $chart_sales_query->selectRaw("to_char(created_at, 'YYYY-MM') as month, SUM(quantity) as total")
                ->groupBy('month')
                ->pluck('total', 'month');
        } else {
            $chart_sales = $chart_sales_query->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, SUM(quantity) as total")
                ->groupBy('month')
                ->pluck('total', 'month');
        }

        $chart_purchases_query = Transaction::where('storage_id', $storage->id)
            ->whereHas('invoice', fn ($q) => $q->where('invocable_type', '!=', Customer::class))
            ->where('created_at', '>=', Carbon::now()->subMonths(5)->startOfMonth());

        if (config('database.default') === 'sqlite') {
            $chart_purchases = $chart_purchases_query->selectRaw("strftime('%Y-%m', created_at) as month, SUM(quantity) as total")
                ->groupBy('month')
                ->pluck('total', 'month');
        } elseif (config('database.default') === 'pgsql') {
            $chart_purchases = $chart_purchases_query->selectRaw("to_char(created_at, 'YYYY-MM') as month, SUM(quantity) as total")
                ->groupBy('month')
                ->pluck('total', 'month');
        } else {
            $chart_purchases = $chart_purchases_query->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, SUM(quantity) as total")
                ->groupBy('month')
                ->pluck('total', 'month');
        }

        $chart_data = [
            'labels' => $months->map(fn ($m) => Carbon::parse($m)->format('M Y'))->toArray(),
            'sales' => $months->map(fn ($m) => $chart_sales->get($m, 0))->toArray(),
            'purchases' => $months->map(fn ($m) => $chart_purchases->get($m, 0))->toArray(),
        ];

        return inertia('Storages/Show', [
            'storage' => $storage,
            'products' => $storage->stock()
                ->when(request('search'), function ($query, $search) {
                    $query->where('name', config('database.default') === 'pgsql' ? 'ILIKE' : 'LIKE', "%{$search}%");
                })
                ->paginate(parent::ELEMENTS_PER_PAGE)
                ->withQueryString(),
            'transactions' => $transactions,
            'all_products' => $storage->stock()->select('products.id', 'products.name')->get(),
            'filters' => request()->only(['from_date', 'to_date', 'product_id', 'type', 'search']),
            'stats' => [
                'sales_count' => (float) $sales_count,
                'purchases_count' => (float) $purchases_count,
                'total_stock_value' => (float) $total_stock_value,
                'unique_products_count' => $storage->stock()->count(),
            ],
            'chart_data' => $chart_data,
        ]);
    }

    public function store(StorageRequest $request)
    {
        Storage::create($request->all());

        return back()->with('success', __('Storage created successfully'));
    }

    public function update(Storage $storage, StorageRequest $request)
    {
        $storage->update($request->all());

        return back()->with('success', __('Storage updated successfully'));
    }

    public function destroy(Storage $storage)
    {
        $this->authorize('delete', $storage);

        $storage->delete();

        return back()->with('success', __('Storage Deleted successfully'));
    }
}
