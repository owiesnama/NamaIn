<?php

namespace App\Exports\Reports;

use App\Exports\Concerns\WithExportStyles;
use App\Queries\Reports\CustomerAgingQuery;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;

class CustomerAgingExport implements FromArray, WithHeadings, WithStyles
{
    use WithExportStyles;

    public function __construct(protected array $filters = []) {}

    public function array(): array
    {
        $customerId = isset($this->filters['customer']) ? (int) $this->filters['customer'] : null;

        return (new CustomerAgingQuery)->getData($customerId);
    }

    public function headings(): array
    {
        return [__('Customer ID'), __('Customer'), __('0-30 Days'), __('31-60 Days'), __('61-90 Days'), __('90+ Days'), __('Total')];
    }
}
