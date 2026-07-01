<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Storage;
use App\Queries\Reports\InventoryValuationQuery;
use Illuminate\Http\Request;

class InventoryValuationController extends Controller
{
    public function index(Request $request, InventoryValuationQuery $query)
    {
        $storageId = $request->input('storage') ? (int) $request->input('storage') : null;
        $categoryId = $request->input('category') ? (int) $request->input('category') : null;

        return inertia('Reports/InventoryValuation', [
            'data' => $query->get($storageId, $categoryId),
            'summary' => $query->summary($storageId, $categoryId),
            'filters' => $request->only(['storage', 'category']),
            'storages' => fn () => Storage::pluck('name', 'id'),
            'categories' => fn () => Category::pluck('name', 'id'),
        ]);
    }
}
