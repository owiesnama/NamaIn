<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Stock\ExecuteStockTransferAction;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStockTransferRequest;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\Storage;
use Illuminate\Support\Facades\DB;

class StockTransfersController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', StockTransfer::class);

        return inertia('StockTransfers/Index', [
            'transfers' => StockTransfer::with(['fromStorage', 'toStorage'])
                ->withCount('lines')
                ->latest()
                ->paginate(10),
        ]);
    }

    public function create()
    {
        $this->authorize('create', StockTransfer::class);

        return inertia('StockTransfers/Create', [
            'storages' => Storage::all(),
            'products' => Product::all(),
        ]);
    }

    public function store(StoreStockTransferRequest $request, ExecuteStockTransferAction $action)
    {
        $this->authorize('create', StockTransfer::class);

        $validated = $request->validated();

        $transfer = DB::transaction(function () use ($validated) {
            $transfer = StockTransfer::create([
                'tenant_id' => currentTenant()->id,
                'from_storage_id' => $validated['from_storage_id'],
                'to_storage_id' => $validated['to_storage_id'],
                'created_by' => auth()->id(),
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                $transfer->lines()->create([
                    'tenant_id' => $transfer->tenant_id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            return $transfer;
        });

        try {
            $action->execute($transfer, auth()->user());
        } catch (InsufficientStockException $e) {
            return redirect()->route('stock-transfers.show', $transfer)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('stock-transfers.show', $transfer)
            ->with('success', __('Stock transfer completed successfully'));
    }

    public function show(StockTransfer $transfer)
    {
        $this->authorize('view', $transfer);

        $transfer->load([
            'fromStorage',
            'toStorage',
            'creator',
            'lines.product' => fn ($q) => $q->select('products.id', 'products.name',),
        ]);

        return inertia('StockTransfers/Show', [
            'transfer' => $transfer,
        ]);
    }
}
