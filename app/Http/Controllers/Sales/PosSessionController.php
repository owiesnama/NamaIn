<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Pos\ClosePosSessionAction;
use App\Actions\Pos\FindReplenishmentSourceAction;
use App\Actions\Pos\OpenPosSessionAction;
use App\Enums\StorageType;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\Storage;
use Illuminate\Http\Request;

class PosSessionController extends Controller
{
    public function show(FindReplenishmentSourceAction $replenishmentAction)
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

        return inertia('Pos/Session', [
            'session' => $session->load(['storage', 'openedBy']),
            'products' => Product::with('units')->get()->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => ($product->price ?? $product->cost ?? 0) / 100,
                'sale_point_qty' => $storage->quantityOf($product),
                'replenishment' => $this->buildReplenishmentInfo($product, $replenishmentAction),
                'units' => $product->units,
            ]),
            'customers' => Customer::where('is_system', false)->get(),
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
