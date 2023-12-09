<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierRequest;
use App\Models\Supplier;

class SuppliersController extends Controller
{
    public function index()
    {
        return inertia('Suppliers/Index', [
            'suppliers' => Supplier::search(request('search'))
                ->trash(request('status'))
                ->latest()
                ->paginate(parent::ELEMENTS_PER_PAGE)
                ->withQueryString(),
        ]);
    }

    public function store(SupplierRequest $request)
    {
        Supplier::create($request->all());

        return redirect()->route('suppliers.index')
            ->with('success', 'Supplier Created Successfully');
    }

    public function update(Supplier $supplier, SupplierRequest $request)
    {
        $supplier->update($request->all());

        return back()->with('success', 'Supplier updated successfully');
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return back()->with('success', 'Supplier Deleted successfully');
    }
}
