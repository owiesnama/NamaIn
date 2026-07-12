<?php

namespace App\Reports;

use App\Models\Storage;
use App\Queries\Reports\NegativeStockQuery;
use Illuminate\Http\Request;

class NegativeStockReport implements Report
{
    public function __construct(private NegativeStockQuery $query) {}

    public function page(): string
    {
        return 'Reports/NegativeStock';
    }

    public function props(Request $request): array
    {
        $storageId = $request->input('storage') ? (int) $request->input('storage') : null;

        return [
            'data' => $this->query->get($storageId),
            'summary' => $this->query->summary($storageId),
            'filters' => $request->only(['storage']),
            'storages' => fn () => Storage::pluck('name', 'id'),
        ];
    }
}
