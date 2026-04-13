<?php

namespace App\Exports;

use App\Models\Supplier;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SupplierExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @return Collection
     */
    public function collection()
    {
        return Supplier::with('categories')->get();
    }

    public function headings(): array
    {
        return [
            'name',
            'address',
            'phone_number',
            'categories',
        ];
    }

    public function map($supplier): array
    {
        return [
            $supplier->name,
            $supplier->address,
            $supplier->phone_number,
            $supplier->categories->pluck('name')->implode(','),
        ];
    }
}
