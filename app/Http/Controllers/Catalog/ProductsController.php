<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\SyncCategoriesAction;
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
            'storages' => Storage::select('id', 'name')->orderBy('name')->get(),
            'products' => Product::filter($filter)
                ->with(['units', 'categories', 'stock'])
                ->withStockAggregates()
                ->orderBy(request('sort_by', 'quantity_on_hand'), request('sort_order', 'desc'))
                ->paginate(request('layout') === 'cards' ? 24 : parent::ELEMENTS_PER_PAGE)
                ->withQueryString(),
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

    public function store(ProductRequest $request, SyncCategoriesAction $syncCategoriesAction)
    {
        $this->authorize('create', Product::class);

        $product = Product::create($request->safe()->except(['units', 'categories']));

        $units = $request->get('units');

        if (empty($units)) {
            $product->units()->create(['name' => __('Base Unit'), 'conversion_factor' => 1]);
        } else {
            $product->syncUnits($units);
        }

        $syncCategoriesAction->handle($product, $request->get('categories'), 'product');

        return redirect()->route('products.index')->with('success', __('Product created successfully.'));
    }

    public function quickUpdate(Product $product, QuickUpdateProductRequest $request)
    {
        $this->authorize('update', $product);

        $product->update($request->validated());

        return back()->with('success', __('Product updated successfully'));
    }

    public function update(Product $product, ProductRequest $request, SyncCategoriesAction $syncCategoriesAction)
    {
        $this->authorize('update', $product);

        $product->update($request->safe()->except(['units', 'categories']));

        $product->syncUnits($request->get('units'));

        $syncCategoriesAction->handle($product, $request->get('categories'), 'product');

        return back()->with('success', __('Product updated successfully'));
    }

    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);

        $product->delete();

        return back()->with('success', 'Product Deleted successfully');
    }
}
