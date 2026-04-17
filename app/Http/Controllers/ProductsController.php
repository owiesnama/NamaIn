<?php

namespace App\Http\Controllers;

use App\Actions\SyncCategoriesAction;
use App\Exports\ProductExport;
use App\Filters\ProductFilter;
use App\Http\Requests\ProductRequest;
use App\Imports\ProductImport;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Transaction;
use App\Services\CsvSampleGenerator;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductsController extends Controller
{
    protected array $importHeaders = ['name', 'cost', 'currency', 'expire_date', 'unit_name', 'unit_conversion_factor', 'categories'];

    protected array $importSampleData = ['Example Product', '100', 'SDG', '2026-12-31', 'Box', '10', 'Category1,Category2'];

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

        $query = Transaction::where('product_id', $product->id)
            ->inDateRange(request('from_date'), request('to_date'))
            ->forStorage(request('storage_id'))
            ->filterByType(request('type'));

        return inertia('Products/Show', [
            'product' => $product,
            'transactions' => $query->latest()
                ->with(['invoice.invocable', 'unit', 'storage', 'invoice.transactions.product'])
                ->paginate(10)
                ->withQueryString(),
            'storages' => Storage::all(),
            'filters' => request()->only(['from_date', 'to_date', 'storage_id', 'type']),
            'stats' => [
                'sales_count' => (float) (clone $query)->whereHas('invoice', fn ($q) => $q->where('invocable_type', Customer::class))->sum('base_quantity'),
                'purchases_count' => (float) (clone $query)->whereHas('invoice', fn ($q) => $q->where('invocable_type', '!=', Customer::class))->sum('base_quantity'),
                'current_stock' => $product->quantityOnHand(),
                'available_qty' => $product->availableQuantity(),
                'pending_sales' => $product->pendingSalesQuantity(),
                'pending_purchases' => $product->pendingPurchaseQuantity(),
            ],
            'chart_data' => Transaction::getMonthlyStats($product->id),
            'insights' => $product->getInsights(),
        ]);
    }

    public function store(ProductRequest $request, SyncCategoriesAction $syncCategoriesAction)
    {
        $product = Product::create($request->safe()->except(['units', 'categories']));

        $product->syncUnits($request->get('units'));

        $syncCategoriesAction->execute($product, $request->get('categories'), 'product');

        return redirect()->route('products.index')->with('success', 'Product has been Created Successfully');
    }

    public function update(Product $product, ProductRequest $request, SyncCategoriesAction $syncCategoriesAction)
    {
        $product->update($request->safe()->except(['units', 'categories']));

        $product->syncUnits($request->get('units'));

        $syncCategoriesAction->execute($product, $request->get('categories'), 'product');

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
        return (new CsvSampleGenerator)->generate(
            'product_import_sample.csv',
            $this->importHeaders,
            $this->importSampleData
        );
    }

    public function export()
    {
        return Excel::download(new ProductExport, 'products.xlsx');
    }
}
