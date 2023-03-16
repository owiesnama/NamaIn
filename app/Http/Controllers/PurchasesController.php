<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Storage;
use App\Models\Vendor;

class PurchasesController extends Controller
{
    public function index()
    {
        return inertia('Purchases/Create', [
            'storages' => Storage::all(),
            'products' => Product::all(),
        ]);
    }

    public function store()
    {
        $attributes = request()->validate([
            'total' => 'required|integer',
            'products.*.product' => 'integer|required',
            'products.*.quantity' => 'integer|required',
            'products.*.price' => 'integer|required',
            'products.*.storage' => 'integer|required',
        ]);
        $vendor = Vendor::firstOrCreate([
            'name' => 'Random',
            'address' => 'no-address',
        ]);
        $purchase = Purchase::create([
            'vendor_id' => $vendor->id,
            'total_cost' => $attributes['total']
        ]);
        $purchase->addStock($attributes['products']);
    }
}
