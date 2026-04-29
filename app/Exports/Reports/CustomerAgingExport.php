<?php

namespace App\Exports\Reports;

use App\Queries\Reports\CustomerAgingQuery;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CustomerAgingExport implements FromArray, WithHeadings
{
    public function __construct(protected array $filters = []) {}

    public function array(): array
    {
        $customerId = isset($this->filters['customer']) ? (int) $this->filters['customer'] : null;

        return (new CustomerAgingQuery)->getData($customerId);
    }

    public function headings(): array
    {
        return ['Customer ID', 'Customer', '0-30 Days', '31-60 Days', '61-90 Days', '90+ Days', 'Total'];
    }
}
