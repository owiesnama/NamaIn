<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Stock\RecordAdjustmentAction;
use App\Actions\SyncCategoriesAction;
use App\Exceptions\ManualStockIncreaseNotAllowedException;
use App\Filters\ProductFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Http\Requests\QuickUpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Storage;
use App\Queries\ProductShowQuery;

class ProductsController extends Controller
{
    public function index(ProductFilter $filter)
    {
        $this->authorize('viewAny', Product::class);

        return inertia('Products/Index', [
            'products_count' => Product::count(),
            'categories' => Category::ofType('product')->get(),
            'storages' => Storage::select('id', 'name', 'type')->orderBy('name')->get(),
            'products' => Product::filter($filter)
                ->with(['units', 'categories', 'stock'])
                ->withStockAggregates()
                ->orderBy(request('sort_by', 'quantity_on_hand'), request('sort_order', 'desc'))
                ->paginate(request('layout') === 'cards' ? 24 : parent::ELEMENTS_PER_PAGE)
                ->withQueryString()
                ->through(fn (Product $product) => $product->append(['pending_sales', 'available_qty'])),
        ]);
    }

    public function show(Product $product)
    {
        $this->authorize('view', $product);

        $product->load(['units', 'categories', 'stock']);

        $query = new ProductShowQuery($product, request()->only(['from_date', 'to_date', 'storage_id', 'type']));

        return inertia('Products/Show', [
            'product' => $product,
            'transactions' => $query->transactions(),
            'storages' => Storage::all(),
            'filters' => request()->only(['from_date', 'to_date', 'storage_id', 'type']),
            'stats' => $query->stats(),
            'chart_data' => $query->chartData(),
            'insights' => $product->getInsights(),
        ]);
    }

    public function store(ProductRequest $request, SyncCategoriesAction $syncCategoriesAction, RecordAdjustmentAction $recordAdjustment)
    {
        $this->authorize('create', Product::class);

        $product = Product::create($request->safe()->except(['units', 'categories', 'quantities']));

        $units = $request->get('units');

        if (empty($units)) {
            $product->units()->create(['name' => __('Base Unit'), 'conversion_factor' => 1]);
        } else {
            $product->syncUnits($units);
        }

        $syncCategoriesAction->handle($product, $request->get('categories'), 'product');

        try {
            $this->syncOnHandQuantities($product, $request, $recordAdjustment);
        } catch (ManualStockIncreaseNotAllowedException $e) {
            return redirect()->route('products.index')->with('error', $e->getMessage());
        }

        return redirect()->route('products.index')->with('success', __('Product created successfully.'));
    }

    public function quickUpdate(Product $product, QuickUpdateProductRequest $request)
    {
        $this->authorize('update', $product);

        $product->update($request->validated());

        return back()->with('success', __('Product updated successfully'));
    }

    public function update(Product $product, ProductRequest $request, SyncCategoriesAction $syncCategoriesAction, RecordAdjustmentAction $recordAdjustment)
    {
        $this->authorize('update', $product);

        $product->update($request->safe()->except(['units', 'categories', 'quantities']));

        $product->syncUnits($request->get('units'));

        $syncCategoriesAction->handle($product, $request->get('categories'), 'product');

        try {
            $this->syncOnHandQuantities($product, $request, $recordAdjustment);
        } catch (ManualStockIncreaseNotAllowedException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Product updated successfully'));
    }

    /**
     * Apply directly-edited on-hand quantities, one per storage, as adjustment
     * deltas.
     *
     * The product form never overwrites stock; for each location it records the
     * difference between the requested quantity and the current balance as an
     * adjustment movement, gated by the tenant's inventory strategy.
     */
    private function syncOnHandQuantities(Product $product, ProductRequest $request, RecordAdjustmentAction $recordAdjustment): void
    {
        foreach ($request->collect('quantities') as $row) {
            if (! isset($row['storage_id'], $row['quantity'])) {
                continue;
            }

            $storage = Storage::findOrFail((int) $row['storage_id']);
            $newQuantity = (int) $row['quantity'];

            if ($newQuantity === $storage->quantityOf($product)) {
                continue;
            }

            $recordAdjustment->handle($storage, $product, $newQuantity, 'product_edit', auth()->user());
        }
    }

    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);

        $product->delete();

        return back()->with('success', 'Product Deleted successfully');
    }
}
