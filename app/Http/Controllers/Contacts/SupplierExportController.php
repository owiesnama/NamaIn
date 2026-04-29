<?php

namespace App\Http\Controllers\Contacts;

use App\Actions\RequestExportAction;
use App\Http\Controllers\Controller;

class SupplierExportController extends Controller
{
    public function store(RequestExportAction $action)
    {
        $action->execute('suppliers', 'xlsx');

        return back()->with('flash', [
            'type' => 'export_queued',
            'message' => __('Export queued. You will be notified when it is ready.'),
        ]);
    }
}
