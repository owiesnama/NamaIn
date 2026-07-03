<?php

namespace App\Reports;

use App\Models\Supplier;
use App\Queries\Reports\SupplierAgingQuery;
use Illuminate\Http\Request;

class SupplierAgingReport implements Report
{
    public function __construct(private SupplierAgingQuery $query) {}

    public function page(): string
    {
        return 'Reports/SupplierAging';
    }

    public function props(Request $request): array
    {
        $supplierId = $request->input('supplier') ? (int) $request->input('supplier') : null;

        return [
            'data' => $this->query->get($supplierId),
            'summary' => $this->query->summary($supplierId),
            'filters' => $request->only(['supplier']),
            'suppliers' => fn () => Supplier::pluck('name', 'id'),
        ];
    }
}
