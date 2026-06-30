<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\StockTransfer;
use Inertia\Response;

class StockTransferPrintController extends Controller
{
    public function show(StockTransfer $transfer): Response
    {
        $this->authorize('view', $transfer);

        $transfer->load([
            'fromStorage',
            'toStorage',
            'creator',
            'lines.product' => fn ($query) => $query->select('products.id', 'products.name'),
        ]);

        return inertia('StockTransfers/Print', [
            'transfer' => $transfer,
        ]);
    }
}
