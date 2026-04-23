<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Pos\FindReplenishmentSourceAction;
use App\Actions\StoreSaleAction;
use App\Enums\PaymentMethod;
use App\Enums\StorageType;
use App\Filters\InvoiceFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateInvoiceRequest;
use App\Models\Bank;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\Storage;

class SalesController extends Controller
{
    public function index(InvoiceFilter $filter)
    {
        return inertia('Sales/Index', [
            'invoices' => Invoice::forCustomer()
                ->notFromPos()
                ->filter($filter)
                ->when(request('sort_by'), function ($query, $sortBy) {
                    $query->orderBy(in_array($sortBy, ['id', 'created_at', 'total']) ? $sortBy : 'created_at', request('sort_order', 'desc'));
                }, function ($query) {
                    $query->latest();
                })
                ->with(['transactions.product', 'transactions.unit', 'invocable'])
                ->paginate(10)
                ->withQueryString(),
            'storages' => Storage::all(),
        ]);
    }

    public function create()
    {
        $query = Customer::query()->where('is_system', false);

        if (request('customer')) {
            $query->search(request('customer'));
        }

        return inertia('Sales/Create', [
            'products' => Product::with('units')->get(),
            'customers' => $query->latest()->limit(10)->get(),
            'payment_methods' => PaymentMethod::casesWithLabels(),
            'banks' => Bank::all(),
        ]);
    }

    public function store(CreateInvoiceRequest $request, StoreSaleAction $storeSale)
    {
        $storeSale->handle(collect($request->all()), $request);

        return redirect()->route('sales.index')->with('success', __('Sale created successfully'));
    }

    public function pos(FindReplenishmentSourceAction $replenishmentAction)
    {
        $storage = currentTenant()->storages()->where('type', StorageType::SALE_POINT)->first();

        if (! $storage) {
            return redirect()->route('storages.index')->with('error', __('No sale point storage found.'));
        }

        $session = PosSession::where('storage_id', $storage->id)
            ->whereNull('closed_at')
            ->first();

        if (! $session) {
            return inertia('Pos/Open', [
                'storage' => $storage,
            ]);
        }

        return inertia('Pos/Session', [
            'session' => $session->load(['storage', 'openedBy']),
            'products' => Product::with('units')->get()->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price ?? $product->cost ?? 0,
                'sale_point_qty' => $storage->quantityOf($product),
                'replenishment' => $this->buildReplenishmentInfo($product, $replenishmentAction),
                'units' => $product->units,
            ]),
            'customers' => Customer::where('is_system', false)->get(),
        ]);
    }

    private function buildReplenishmentInfo(Product $product, FindReplenishmentSourceAction $action): ?array
    {
        $source = $action->handle($product, 1);

        if (! $source) {
            return null;
        }

        return [
            'warehouse_id' => $source->warehouse->id,
            'warehouse_name' => $source->warehouse->name,
            'available_qty' => $source->availableQuantity,
        ];
    }
}
