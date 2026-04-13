<?php

namespace App\Exports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @return Collection
     */
    public function collection()
    {
        return Product::with(['categories', 'units'])->get();
    }

    public function headings(): array
    {
        return [
            'name',
            'cost',
            'currency',
            'expire_date',
            'categories',
            'units',
        ];
    }

    public function map($product): array
    {
        return [
            $product->name,
            $product->cost,
            $product->currency,
            $product->expire_date,
            $product->categories->pluck('name')->implode(','),
            $product->units->map(fn ($unit) => "{$unit->name}({$unit->conversion_factor})")->implode(','),
        ];
    }
}
