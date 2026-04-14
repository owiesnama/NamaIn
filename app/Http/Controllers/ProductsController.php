<?php

namespace App\Http\Controllers;

use App\Exports\ProductExport;
use App\Filters\ProductFilter;
use App\Http\Requests\ProductRequest;
use App\Imports\ProductImport;
use App\Models\Category;
use App\Models\Product;
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
                ->with(['units', 'categories'])
                ->orderBy(request('sort_by', 'created_at'), request('sort_order', 'desc'))
                ->paginate(parent::ELEMENTS_PER_PAGE)
                ->withQueryString(),
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
