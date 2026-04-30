<?php

namespace App\Exports\Reports;

use App\Exports\Concerns\WithExportStyles;
use App\Queries\Reports\SupplierAgingQuery;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;

class SupplierAgingExport implements FromArray, WithHeadings, WithStyles
{
    use WithExportStyles;

    public function __construct(protected array $filters = []) {}

    public function array(): array
    {
        $supplierId = isset($this->filters['supplier']) ? (int) $this->filters['supplier'] : null;

        return (new SupplierAgingQuery)->getData($supplierId);
    }

    public function headings(): array
    {
        return [__('Supplier ID'), __('Supplier'), __('0-30 Days'), __('31-60 Days'), __('61-90 Days'), __('90+ Days'), __('Total')];
    }
}
