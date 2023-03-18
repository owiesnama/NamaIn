<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateInvoiceRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;

class SalesController extends Controller
{
    public function index()
    {
        return inertia('Sales/Index', [
            'invoices' => Invoice::where('invoicable_type', Customer::class)->with('details')->paginate(10)->withQueryString(),
        ]);
    }

    public function create()
    {
        return inertia('Sales/Create', [
            'products' => Product::all(),
        ]);
    }

    public function store(CreateInvoiceRequest $request)
    {
        Invoice::sale(collect($request->all()))
            ->save();

        return redirect()->route('salse.index');
    }
}
