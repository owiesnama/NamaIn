<?php

namespace App\Exports;

use App\Exports\Concerns\WithExportStyles;
use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;

class ProductExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    use WithExportStyles;

    public function __construct(protected array $filters = []) {}

    public function collection(): Collection
    {
        return Product::with(['categories', 'units'])->get();
    }

    public function headings(): array
    {
        return [
            __('Name'),
            __('Cost'),
            __('Currency'),
            __('Expire Date'),
            __('Categories'),
            __('Units'),
        ];
    }

    public function map($product): array
    {
        return [
            $product->name,
            $product->cost,
            $product->currency,
            $product->expire_date,
            $product->categories->pluck('name')->implode(', '),
            $product->units->map(fn ($unit) => "{$unit->name}({$unit->conversion_factor})")->implode(', '),
        ];
    }
}
