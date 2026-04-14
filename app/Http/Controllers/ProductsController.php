<?php

namespace App\Http\Controllers;

use App\Exports\ProductExport;
use App\Filters\ProductFilter;
use App\Http\Requests\ProductRequest;
use App\Imports\ProductImport;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Transaction;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductsController extends Controller
{
    public function index(ProductFilter $filter)
    {
        return inertia('Products/Index', [
            'products_count' => Product::count(),
            'categories' => Category::ofType('product')->get(),
            'products' => Product::filter($filter)
                ->with(['units', 'categories', 'stock'])
                ->orderBy(request('sort_by', 'created_at'), request('sort_order', 'desc'))
                ->paginate(parent::ELEMENTS_PER_PAGE)
                ->withQueryString(),
        ]);
    }

    public function show(Product $product)
    {
        $product->load(['units', 'categories', 'stock']);

        $query = Transaction::where('product_id', $product->id);

        if (request('from_date')) {
            $query->whereDate('created_at', '>=', request('from_date'));
        }
        if (request('to_date')) {
            $query->whereDate('created_at', '<=', request('to_date'));
        }
        if (request('storage_id')) {
            $query->where('storage_id', request('storage_id'));
        }
        if (request('type') && request('type') !== 'All') {
            if (request('type') === 'Sales') {
                $query->whereHas('invoice', fn ($q) => $q->where('invocable_type', Customer::class));
            } elseif (request('type') === 'Purchases') {
                $query->whereHas('invoice', fn ($q) => $q->where('invocable_type', '!=', Customer::class));
            }
        }

        $transactions = (clone $query)->latest()
            ->with(['invoice.invocable', 'unit', 'storage', 'invoice.transactions.product'])
            ->paginate(10)
            ->withQueryString();

        $sales_count = (clone $query)
            ->whereHas('invoice', fn ($q) => $q->where('invocable_type', Customer::class))
            ->sum('base_quantity');

        $purchases_count = (clone $query)
            ->whereHas('invoice', fn ($q) => $q->where('invocable_type', '!=', Customer::class))
            ->sum('base_quantity');

        // Chart Data (Last 6 Months)
        $months = collect();
        for ($i = 5; $i >= 0; $i--) {
            $months->push(Carbon::now()->subMonths($i)->format('Y-m'));
        }

        $chart_sales_query = Transaction::where('product_id', $product->id)
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

        $chart_purchases_query = Transaction::where('product_id', $product->id)
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

        $insights = [];
        $qtyOnHand = $product->quantityOnHand();
        $pendingSales = $product->pendingSalesQuantity();
        $availableQty = $product->availableQuantity();
        $pendingPurchases = $product->pendingPurchaseQuantity();

        if ($pendingSales > $qtyOnHand) {
            $insights[] = [
                'type' => 'danger',
                'message' => __('Product overcommitted: :units units needed', ['units' => number_format($pendingSales - $qtyOnHand, 2)]),
            ];
        }

        if ($qtyOnHand == 0) {
            $insights[] = [
                'type' => 'danger',
                'message' => __('Out of Stock'),
            ];
        } elseif ($availableQty <= $product->alert_quantity && $availableQty > 0) {
            $insights[] = [
                'type' => 'warning',
                'message' => __('Low stock alert: :units units remaining', ['units' => number_format($availableQty, 2)]),
            ];
        }

        if ($pendingPurchases > 0) {
            $insights[] = [
                'type' => 'info',
                'message' => __('Incoming stock: :units units expected', ['units' => number_format($pendingPurchases, 2)]),
            ];
        }

        return inertia('Products/Show', [
            'product' => $product,
            'transactions' => $transactions,
            'storages' => Storage::all(),
            'filters' => request()->only(['from_date', 'to_date', 'storage_id', 'type']),
            'stats' => [
                'sales_count' => (float) $sales_count,
                'purchases_count' => (float) $purchases_count,
                'current_stock' => $qtyOnHand,
                'available_qty' => $availableQty,
                'pending_sales' => $pendingSales,
                'pending_purchases' => $pendingPurchases,
            ],
            'chart_data' => $chart_data,
            'insights' => $insights,
        ]);
    }

    public function store(ProductRequest $request)
    {
        $product = Product::create($request->all());
        $product->units()->createMany($request->get('units'));

        $type = 'product';
        $categoryIds = collect($request->get('categories'))->map(function ($category) use ($type) {
            return Category::firstOrCreate(
                ['id' => is_numeric($category['id']) ? $category['id'] : null],
                ['name' => $category['name'], 'type' => $type]
            )->id;
        });
        $product->categories()->sync($categoryIds);

        return redirect()->route('products.index')->with('success', 'Product has been Created Successfully');
    }

    public function update(Product $product, ProductRequest $request)
    {
        $product->update($request->all());
        $product->units()->delete();
        $product->units()->createMany($request->get('units'));

        $type = 'product';
        $categoryIds = collect($request->get('categories'))->map(function ($category) use ($type) {
            if (isset($category['id']) && is_numeric($category['id'])) {
                return $category['id'];
            }

            return Category::firstOrCreate(['name' => $category['name'], 'type' => $type])->id;
        });

        $product->categories()->sync($categoryIds);

        return back()->with('success', __('Product updated successfully'));
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return back()->with('success', 'Product Deleted successfully');
    }

    public function import()
    {
        Excel::import(new ProductImport, request()->file('file'));

        return back()->with('success', 'Imported successfully');
    }

    public function importSample(): BinaryFileResponse
    {
        $filePath = storage_path('app/public/product_import_sample.csv');
        $headers = ['name', 'cost', 'currency', 'expire_date', 'unit_name', 'unit_conversion_factor', 'categories'];

        $handle = fopen($filePath, 'w');
        fputcsv($handle, $headers);
        fputcsv($handle, ['Example Product', '100', 'USD', '2026-12-31', 'Box', '10', 'Category1,Category2']);
        fclose($handle);

        return response()->download($filePath)->deleteFileAfterSend();
    }

    public function export()
    {
        return Excel::download(new ProductExport, 'products.xlsx');
    }
}
