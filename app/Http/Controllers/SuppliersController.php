<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierRequest;
use App\Imports\SupplierImport;
use App\Models\Category;
use App\Models\Supplier;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SuppliersController extends Controller
{
    public function index()
    {
        return inertia('Suppliers/Index', [
            'suppliers' => Supplier::search(request('search'))
                ->trash(request('status'))
                ->when(request('category'), fn ($query, $category) => $query->whereRelation('categories', 'categories.id', $category))
                ->when(request('sort_by'), function ($query, $sortBy) {
                    $query->orderBy(in_array($sortBy, ['name', 'created_at']) ? $sortBy : 'created_at', request('sort_order', 'desc'));
                }, function ($query) {
                    $query->latest();
                })
                ->with('categories')
                ->paginate(parent::ELEMENTS_PER_PAGE)
                ->withQueryString(),
            'categories' => Category::all(),
        ]);
    }

    public function store(SupplierRequest $request)
    {
        $supplier = Supplier::create($request->all());

        $categoryIds = collect($request->get('categories'))->map(function ($category) {
            return Category::firstOrCreate(
                ['id' => is_numeric($category['id']) ? $category['id'] : null],
                ['name' => $category['name']]
            )->id;
        });
        $supplier->categories()->sync($categoryIds);

        return redirect()->route('suppliers.index')
            ->with('success', 'Supplier Created Successfully');
    }

    public function update(Supplier $supplier, SupplierRequest $request)
    {
        $supplier->update($request->all());

        $categoryIds = collect($request->get('categories'))->map(function ($category) {
            return Category::firstOrCreate(
                ['id' => is_numeric($category['id']) ? $category['id'] : null],
                ['name' => $category['name']]
            )->id;
        });
        $supplier->categories()->sync($categoryIds);

        return back()->with('success', 'Supplier updated successfully');
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return back()->with('success', 'Supplier Deleted successfully');
    }

    public function import()
    {
        Excel::import(new SupplierImport, request()->file('file'));

        return back()->with('success', 'Imported successfully');
    }

    public function importSample(): BinaryFileResponse
    {
        $filePath = storage_path('app/public/supplier_import_sample.csv');
        $headers = ['name', 'address', 'phone_number'];

        $handle = fopen($filePath, 'w');
        fputcsv($handle, $headers);
        fputcsv($handle, ['Example Supplier', 'Supplier Address 123', '0123456789']);
        fclose($handle);

        return response()->download($filePath)->deleteFileAfterSend();
    }
}
