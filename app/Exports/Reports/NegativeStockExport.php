<?php

namespace App\Exports\Reports;

use App\Exports\Concerns\WithExportStyles;
use App\Queries\Reports\NegativeStockQuery;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;

class NegativeStockExport implements FromArray, WithHeadings, WithStyles
{
    use WithExportStyles;

    public function __construct(protected array $filters = []) {}

    public function array(): array
    {
        $storageId = isset($this->filters['storage']) ? (int) $this->filters['storage'] : null;

        return array_map(fn (array $row) => [
            $row['product_id'],
            $row['product_name'],
            $row['storage_name'],
            $row['quantity'],
            $row['negative_since'],
            $row['days_negative'],
        ], (new NegativeStockQuery)->get($storageId));
    }

    public function headings(): array
    {
        return [__('Product ID'), __('Product'), __('Storage'), __('Quantity'), __('Negative Since'), __('Days Negative')];
    }
}
