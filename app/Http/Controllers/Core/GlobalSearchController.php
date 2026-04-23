<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $search = $request->get('search');

        if (! $search) {
            return response()->json([]);
        }

        $products = Product::search($search)
            ->take(5)
            ->get()
            ->map(fn ($product) => [
                'type' => 'Product',
                'id' => $product->id,
                'name' => $product->name,
                'url' => route('products.index', ['search' => $product->name]),
                'subtext' => $product->cost,
            ]);

        $customers = Customer::search($search)
            ->take(5)
            ->get()
            ->map(fn ($customer) => [
                'type' => 'Customer',
                'id' => $customer->id,
                'name' => $customer->name,
                'url' => route('customers.index', ['search' => $customer->name]),
                'subtext' => $customer->phone_number,
            ]);

        $suppliers = Supplier::search($search)
            ->take(5)
            ->get()
            ->map(fn ($supplier) => [
                'type' => 'Supplier',
                'id' => $supplier->id,
                'name' => $supplier->name,
                'url' => route('suppliers.index', ['search' => $supplier->name]),
                'subtext' => $supplier->phone_number,
            ]);

        $invoices = Invoice::search($search)
            ->take(5)
            ->with('invocable')
            ->get()
            ->map(fn ($invoice) => [
                'type' => 'Invoice',
                'id' => $invoice->id,
                'name' => '#'.$invoice->serial_number,
                'url' => route('invoices.show', $invoice->id),
                'subtext' => $invoice->invocable?->name ?? '',
            ]);

        return response()->json(
            collect($products)->concat($customers)->concat($suppliers)->concat($invoices)
        );
    }
}
