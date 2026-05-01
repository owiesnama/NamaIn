<?php

namespace App\Imports;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class SupplierImport implements ToModel, WithHeadingRow
{
    /**
     * @return Model|null
     */
    public function model(array $row)
    {
        return new Supplier([
            'name' => $row['name'],
            'address' => $row['address'] ?? '',
            'phone_number' => $row['phone_number'] ?? '',
            'opening_balance' => $row['opening_balance'] ?? 0,
        ]);
    }
}
