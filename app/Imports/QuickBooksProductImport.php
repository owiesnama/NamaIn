<?php

namespace App\Imports;

use App\Imports\Concerns\ParsesSpreadsheetDates;
use App\Imports\Concerns\TracksImportProgress;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Validators\Failure;

class QuickBooksProductImport implements OnEachRow, WithHeadingRow, WithMultipleSheets
{
    use ParsesSpreadsheetDates;
    use TracksImportProgress;

    public function sheets(): array
    {
        return [1 => $this];
    }

    public function onRow(Row $row): void
    {
        $data = $row->toArray();

        if (($data['active_status'] ?? '') !== 'Active') {
            return;
        }

        if (($data['type'] ?? '') !== 'Inventory Part') {
            return;
        }

        $itemName = trim($data['item'] ?? '');

        if ($itemName === '' || preg_match('/^[.\s]+$/', $itemName)) {
            return;
        }

        $validator = Validator::make($data, [
            'item' => 'required|string',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $attribute => $errors) {
                $this->failures[] = new Failure($row->getIndex(), $attribute, $errors, $data);
            }

            return;
        }

        [$name, $categoryName] = $this->parseItemName($itemName);

        $cost = (int) ($data['cost'] ?? 0);
        $price = (int) ($data['price'] ?? 0);

        $product = Product::create([
            'name' => $name,
            'cost' => $cost,
            'price' => $price > 0 ? $price : $cost,
            'expire_date' => $this->parseExpireDate($data),
            'currency' => preference('currency', 'SDG'),
        ]);

        if ($categoryName) {
            $category = Category::firstOrCreate(['name' => $categoryName]);
            $product->categories()->sync([$category->id]);
        }

        $unitName = $this->parseUnitName($data['um'] ?? null);

        if ($unitName) {
            $product->units()->create([
                'name' => $unitName,
                'conversion_factor' => 1,
            ]);
        }

        $this->incrementRowCount();
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function parseItemName(string $item): array
    {
        if (str_contains($item, ':')) {
            $parts = explode(':', $item, 2);

            return [trim($parts[1]), trim($parts[0])];
        }

        return [$item, null];
    }

    private function parseExpireDate(array $data): ?Carbon
    {
        // maatwebsite normalizes Arabic header تاريخ الانتهاء to tarykh_alanthaaa
        return $this->parseSpreadsheetDate($data['tarykh_alanthaaa'] ?? null);
    }

    /**
     * Parse unit name from QB format like "each (قطعه)" → "each"
     */
    private function parseUnitName(?string $raw): ?string
    {
        if (! $raw || trim($raw) === '') {
            return null;
        }

        $name = preg_replace('/\s*\(.*\)$/', '', trim($raw));

        return $name !== '' ? $name : null;
    }
}
