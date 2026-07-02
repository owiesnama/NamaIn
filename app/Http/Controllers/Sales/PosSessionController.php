<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Pos\ClosePosSessionAction;
use App\Actions\Pos\OpenPosSessionAction;
use App\Http\Controllers\Controller;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\Storage;
use App\Queries\DashboardStatsQuery;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class PosSessionController extends Controller
{
    public function show(DashboardStatsQuery $stats)
    {
        $this->authorize('viewAny', PosSession::class);

        $salePoints = currentTenant()->storages()->salePoints()->get();

        if ($salePoints->isEmpty()) {
            return redirect()->route('storages.index')->with('error', __('No sale point storage found.'));
        }

        $storage = $this->resolveSalePoint($salePoints, request('storage_id'));
        $salePointOptions = $this->presentSalePoints($salePoints);

        $session = PosSession::where('storage_id', $storage->id)
            ->whereNull('closed_at')
            ->first();

        if (! $session) {
            return inertia('Pos/Open', [
                'storage' => $storage,
                'salePoints' => $salePointOptions,
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
            'salePoints' => $salePointOptions,
            'selectedStorageId' => $storage->id,
            'hotProducts' => $this->hotProducts($storage, $stats),
            'initialProducts' => $products->through(fn (Product $product) => $this->presentProduct($product, $replenishment)),
            'session_stats' => [
                'opening_float' => $session->opening_float / 100,
                'cash_sales_total' => $session->cashSalesTotal() / 100,
                'expected_closing_float' => $session->expectedClosingFloat() / 100,
            ],
        ]);
    }

    /**
     * Resolve the sale point to drive the session, falling back to the first
     * sale point when the requested one is missing or not a valid sale point.
     */
    private function resolveSalePoint(Collection $salePoints, mixed $requestedId): Storage
    {
        if ($requestedId) {
            $selected = $salePoints->firstWhere('id', (int) $requestedId);

            if ($selected) {
                return $selected;
            }
        }

        return $salePoints->first();
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string}>
     */
    private function presentSalePoints(Collection $salePoints): \Illuminate\Support\Collection
    {
        return $salePoints->map(fn (Storage $salePoint) => [
            'id' => $salePoint->id,
            'name' => $salePoint->name,
        ])->values();
    }

    /**
     * Most-sold products for the given sale point, shaped like the product grid rows.
     *
     * @return array<int, array<string, mixed>>
     */
    private function hotProducts(Storage $storage, DashboardStatsQuery $stats): array
    {
        $orderedProductIds = $stats->topSellingProducts($storage->id, 8)
            ->pluck('product_id')
            ->filter()
            ->values();

        if ($orderedProductIds->isEmpty()) {
            return [];
        }

        $products = Product::with('units')
            ->whereIn('products.id', $orderedProductIds)
            ->leftJoin('stocks', function ($join) use ($storage) {
                $join->on('stocks.product_id', '=', 'products.id')
                    ->where('stocks.storage_id', $storage->id)
                    ->whereNull('stocks.deleted_at');
            })
            ->select('products.*', 'stocks.quantity as sale_point_qty')
            ->get()
            ->sortBy(fn (Product $product) => $orderedProductIds->search($product->id))
            ->values();

        $replenishment = $this->batchReplenishmentInfo($products->pluck('id'));

        return $products->map(fn (Product $product) => $this->presentProduct($product, $replenishment))->all();
    }

    /**
     * Shape a product row for the POS product grid.
     *
     * @param  array<int, array{warehouse_id: int, warehouse_name: string, available_qty: int}>  $replenishment
     * @return array<string, mixed>
     */
    private function presentProduct(Product $product, array $replenishment): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
            'sale_point_qty' => (int) ($product->sale_point_qty ?? 0),
            'replenishment' => $replenishment[$product->id] ?? null,
            'units' => $product->units,
        ];
    }

    public function store(Request $request, OpenPosSessionAction $action)
    {
        $this->authorize('create', PosSession::class);
        $request->validate([
            'storage_id' => 'required|exists:storages,id',
            'opening_float' => 'required|numeric|min:0',
        ]);

        $storage = Storage::findOrFail($request->storage_id);

        $action->handle($storage, $request->opening_float * 100, auth()->user());

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

        $action->handle($session, $request->closing_float * 100, auth()->user());

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
