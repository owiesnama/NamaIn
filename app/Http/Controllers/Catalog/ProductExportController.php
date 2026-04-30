<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\RequestExportAction;
use App\Http\Controllers\Controller;

class ProductExportController extends Controller
{
    public function store(RequestExportAction $action)
    {
        $action->execute('products', 'xlsx');

        return back()->with('flash', [
            'type' => 'export_queued',
            'message' => __('Export queued. You will be notified when it is ready.'),
        ]);
    }
}
