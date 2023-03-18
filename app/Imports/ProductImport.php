<?php

namespace App\Imports;

use App\Models\Product;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\ToModel;

class ProductImport implements ToModel
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        return new Product([
            'name' => $row[0],
            'price' => $row[1],
            'cost' => $row[2],
            'expire_date' => Carbon::parse($row[3]),
        ]);
    }
}
