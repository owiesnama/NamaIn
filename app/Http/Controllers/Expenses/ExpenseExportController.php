<?php

namespace App\Http\Controllers\Expenses;

use App\Actions\RequestExportAction;
use App\Http\Controllers\Controller;
use App\Models\Expense;

class ExpenseExportController extends Controller
{
    public function store(RequestExportAction $action)
    {
        $this->authorize('viewAny', Expense::class);

        $action->execute('expenses', 'xlsx', request()->only(['search', 'category', 'status', 'from_date', 'to_date']));

        return back()->with('flash', [
            'type' => 'export_queued',
            'message' => __('Export queued. You will be notified when it is ready.'),
        ]);
    }
}
