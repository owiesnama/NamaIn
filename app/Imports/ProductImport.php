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
    public function model(array $prodcut)
    {
        return new Product([
            'name' => $prodcut['name'],
            'price' => $prodcut['price'],
            'cost' => $prodcut['cost'],
            'expire_date' => Carbon::parse($prodcut['expire_date']),
        ]);
    }
}
