<?php

namespace App\Reports;

use App\Models\Category;
use App\Models\Storage;
use App\Queries\Reports\InventoryValuationQuery;
use Illuminate\Http\Request;

class InventoryValuationReport implements Report
{
    public function __construct(private InventoryValuationQuery $query) {}

    public function page(): string
    {
        return 'Reports/InventoryValuation';
    }

    public function props(Request $request): array
    {
        $storageId = $request->input('storage') ? (int) $request->input('storage') : null;
        $categoryId = $request->input('category') ? (int) $request->input('category') : null;

        return [
            'data' => $this->query->get($storageId, $categoryId),
            'summary' => $this->query->summary($storageId, $categoryId),
            'filters' => $request->only(['storage', 'category']),
            'storages' => fn () => Storage::pluck('name', 'id'),
            'categories' => fn () => Category::pluck('name', 'id'),
        ];
    }
}
