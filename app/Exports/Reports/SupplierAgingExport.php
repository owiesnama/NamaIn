<?php

namespace App\Exports\Reports;

use App\Queries\Reports\SupplierAgingQuery;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SupplierAgingExport implements FromArray, WithHeadings
{
    public function __construct(protected array $filters = []) {}

    public function array(): array
    {
        $supplierId = isset($this->filters['supplier']) ? (int) $this->filters['supplier'] : null;

        return (new SupplierAgingQuery)->getData($supplierId);
    }

    public function headings(): array
    {
        return ['Supplier ID', 'Supplier', '0-30 Days', '31-60 Days', '61-90 Days', '90+ Days', 'Total'];
    }
}
