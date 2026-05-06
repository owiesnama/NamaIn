<?php

namespace App\Imports;

use App\Imports\Concerns\NormalizesArabicEncoding;
use App\Imports\Concerns\ParsesSpreadsheetDates;
use App\Imports\Concerns\TracksImportProgress;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Validators\Failure;

class ProductImport implements OnEachRow, WithHeadingRow
{
    use NormalizesArabicEncoding;
    use ParsesSpreadsheetDates;
    use TracksImportProgress;

    public function onRow(Row $row): void
    {
        $data = $row->toArray();

        $validator = Validator::make($data, [
            'name' => 'required|string',
            'cost' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $attribute => $errors) {
                $this->failures[] = new Failure($row->getIndex(), $attribute, $errors, $data);
            }

            return;
        }

        $expireDate = $this->parseSpreadsheetDate($data['expire_date'] ?? null);

        if (! empty($data['expire_date']) && $expireDate === null) {
            $this->failures[] = new Failure(
                $row->getIndex(),
                'expire_date',
                [__('Expiry date must be a valid date.')],
                $data,
            );

            return;
        }

        $this->incrementRowCount();

        $product = Product::create([
            'name' => $this->normalizeText($data['name']),
            'cost' => $data['cost'] ?? null,
            'price' => $data['price'] ?? $data['cost'] ?? 0,
            'expire_date' => $expireDate,
            'currency' => $data['currency'] ?? preference('currency', '$'),
        ]);

        if (! empty($data['unit_name'])) {
            $product->units()->create([
                'name' => $this->normalizeText($data['unit_name']),
                'conversion_factor' => $data['unit_conversion_factor'] ?? 1,
            ]);
        }

        if (! empty($data['categories'])) {
            $categories = explode(',', $data['categories']);
            $categoryIds = collect($categories)->map(function ($name) {
                return Category::firstOrCreate(['name' => $this->normalizeText(trim($name))])->id;
            });
            $product->categories()->sync($categoryIds);
        }
    }
}
