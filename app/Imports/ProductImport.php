<?php

namespace App\Imports;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Validators\Failure;

class ProductImport implements OnEachRow, WithHeadingRow
{
    private int $rowCount = 0;

    /** @var Failure[] */
    private array $failures = [];

    public function onRow(Row $row): void
    {
        $data = $row->toArray();

        $validator = Validator::make($data, [
            'name' => 'required|string',
            'cost' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $attribute => $errors) {
                $this->failures[] = new Failure(
                    $row->getIndex(),
                    $attribute,
                    $errors,
                    $data,
                );
            }

            return;
        }

        $this->rowCount++;

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

    /** @return Failure[] */
    public function failures(): array
    {
        return $this->failures;
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }
}
