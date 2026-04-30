<?php

namespace App\Exports\Reports;

use App\Queries\Reports\InventoryValuationQuery;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class InventoryValuationExport implements FromArray, WithHeadings
{
    public function __construct(protected array $filters = []) {}

    public function array(): array
    {
        $storageId = isset($this->filters['storage']) ? (int) $this->filters['storage'] : null;
        $categoryId = isset($this->filters['category']) ? (int) $this->filters['category'] : null;

        return (new InventoryValuationQuery)->getData($storageId, $categoryId);
    }

    public function headings(): array
    {
        return ['Product ID', 'Product', 'Storage', 'Quantity', 'Avg Cost', 'Total Value'];
    }
}
