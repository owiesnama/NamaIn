<?php

namespace App\Imports;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

class ProductImport implements OnEachRow, WithHeadingRow
{
    public function onRow(Row $row)
    {
        $data = $row->toArray();

        $product = Product::create([
            'name' => $data['name'],
            'cost' => $data['cost'] ?? null,
            'expire_date' => Carbon::parse($data['expire_date']),
            'currency' => $data['currency'] ?? preference('currency', '$'),
        ]);

        if (! empty($data['unit_name'])) {
            $product->units()->create([
                'name' => $data['unit_name'],
                'conversion_factor' => $data['unit_conversion_factor'] ?? 1,
            ]);
        }

        if (! empty($data['categories'])) {
            $categories = explode(',', $data['categories']);
            $categoryIds = collect($categories)->map(function ($name) {
                return Category::firstOrCreate(['name' => trim($name)])->id;
            });
            $product->categories()->sync($categoryIds);
        }
    }
}
