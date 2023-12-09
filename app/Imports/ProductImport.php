<?php

namespace App\Imports;

use App\Models\Product;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductImport implements ToModel, WithHeadingRow
{
    /**
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        return new Product([
            'name' => $row['name'],
            'cost' => $row['cost'] ?? null,
            'expire_date' => Carbon::parse($row['expire_date']),
        ]);
    }
}
