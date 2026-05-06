<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Pos\ClosePosSessionAction;
use App\Actions\Pos\OpenPosSessionAction;
use App\Enums\StorageType;
use App\Http\Controllers\Controller;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\Storage;
use Illuminate\Http\Request;

class PosSessionController extends Controller
{
    public function show()
    {
        $this->authorize('viewAny', PosSession::class);
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

        $products = Product::with('units')
            ->when(request('search'), fn ($q, $search) => $q->where('name', 'ilike', "%{$search}%"))
            ->leftJoin('stocks', function ($join) use ($storage) {
                $join->on('stocks.product_id', '=', 'products.id')
                    ->where('stocks.storage_id', $storage->id)
                    ->whereNull('stocks.deleted_at');
            })
            ->select('products.*', 'stocks.quantity as sale_point_qty')
            ->orderByDesc('stocks.quantity')
            ->orderBy('products.name')
            ->paginate(24)
            ->withQueryString();

        $productIds = $products->getCollection()->pluck('id');
        $replenishment = $this->batchReplenishmentInfo($productIds);

        return inertia('Pos/Session', [
            'session' => $session->load(['storage', 'openedBy']),
            'initialProducts' => $products->through(function (Product $product) use ($replenishment) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->price / 100,
                    'sale_point_qty' => (int) ($product->sale_point_qty ?? 0),
                    'replenishment' => $replenishment[$product->id] ?? null,
                    'units' => $product->units,
                ];
            }),
            'session_stats' => [
                'opening_float' => $session->opening_float / 100,
                'cash_sales_total' => $session->cashSalesTotal() / 100,
                'expected_closing_float' => $session->expectedClosingFloat() / 100,
            ],
        ]);
    }

    public function store(Request $request, OpenPosSessionAction $action)
    {
        $this->authorize('create', PosSession::class);
        $request->validate([
            'storage_id' => 'required|exists:storages,id',
            'opening_float' => 'required|numeric|min:0',
        ]);

        $storage = Storage::findOrFail($request->storage_id);

        $action->execute($storage, $request->opening_float * 100, auth()->user());

        return redirect()->route('pos.index')->with('success', __('POS session opened.'));
    }

    public function destroy(Request $request, ClosePosSessionAction $action)
    {
        $this->authorize('close', PosSession::class);
        $request->validate([
            'session_id' => 'required|exists:pos_sessions,id',
            'closing_float' => 'required|numeric|min:0',
        ]);

        $session = PosSession::findOrFail($request->session_id);

        $action->execute($session, $request->closing_float * 100, auth()->user());

        return redirect()->route('pos.index')->with('success', __('POS session closed.'));
    }

    /**
     * Batch-load replenishment sources for multiple products in a single query.
     *
     * @return array<int, array{warehouse_id: int, warehouse_name: string, available_qty: int}>
     */
    private function batchReplenishmentInfo($productIds): array
    {
        if ($productIds->isEmpty()) {
            return [];
        }

        $rows = Storage::warehouses()
            ->join('stocks', fn ($join) => $join
                ->on('storages.id', '=', 'stocks.storage_id')
                ->whereIn('stocks.product_id', $productIds)
                ->where('stocks.quantity', '>', 0))
            ->select('storages.id as warehouse_id', 'storages.name as warehouse_name', 'stocks.product_id', 'stocks.quantity')
            ->orderByDesc('stocks.quantity')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            if (isset($result[$row->product_id])) {
                continue;
            }

            $result[$row->product_id] = [
                'warehouse_id' => $row->warehouse_id,
                'warehouse_name' => $row->warehouse_name,
                'available_qty' => (int) $row->quantity,
            ];
        }

        return $result;
    }
}
