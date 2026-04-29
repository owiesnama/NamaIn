<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Queries\Reports\SupplierAgingQuery;
use Illuminate\Http\Request;

class SupplierAgingController extends Controller
{
    public function index(Request $request, SupplierAgingQuery $query)
    {
        abort_unless(auth()->user()->hasPermission('reports.view'), 403);

        $supplierId = $request->input('supplier') ? (int) $request->input('supplier') : null;

        return inertia('Reports/SupplierAging', [
            'data' => $query->getData($supplierId),
            'summary' => $query->getSummary($supplierId),
            'filters' => $request->only(['supplier']),
            'suppliers' => fn () => Supplier::pluck('name', 'id'),
        ]);
    }
}
