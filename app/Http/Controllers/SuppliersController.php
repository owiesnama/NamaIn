<?php

namespace App\Http\Controllers;

use App\Models\Supplier;

class SuppliersController extends Controller
{
    public function index()
    {
        return inertia('Suppliers', [
            'suppliers' =>  Supplier::search(request('search'))
                ->latest()
                ->paginate(parent::ELEMENTS_PER_PAGE)
                ->withQueryString()
        ]);
    }


    public function store()
    {
        $attributes = request()->validate([
            'name' => 'required',
            'address' => 'required|string|min:10',
            'phone_number' => 'required|numeric|min:10',
        ]);

        Supplier::create($attributes);

        return redirect()->route('suppliers.index')
            ->with('success', 'Customer Created Successfully');
    }
}
