<?php

namespace App\Exports;

use App\Exports\Concerns\WithExportStyles;
use App\Models\Supplier;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;

class SupplierExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    use WithExportStyles;

    public function __construct(protected array $filters = []) {}

    public function collection(): Collection
    {
        return Supplier::with('categories')->get();
    }

    public function headings(): array
    {
        return [
            __('Name'),
            __('Address'),
            __('Phone Number'),
            __('Categories'),
        ];
    }

    public function map($supplier): array
    {
        return [
            $supplier->name,
            $supplier->address,
            $supplier->phone_number,
            $supplier->categories->pluck('name')->implode(', '),
        ];
    }
}
