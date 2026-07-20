<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\RequestExportAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\BulkExportProductsRequest;
use App\Models\Product;

class BulkExportProductsController extends Controller
{
    public function __invoke(BulkExportProductsRequest $request, RequestExportAction $action)
    {
        $this->authorize('viewAny', Product::class);

        $action->handle('products', 'xlsx', ['ids' => $request->validated('ids')]);

        return back()->with('flash', [
            'type' => 'export_queued',
            'message' => __('Export queued. You will be notified when it is ready.'),
        ]);
    }
}
