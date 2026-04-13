<?php

namespace App\Imports;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CustomerImport implements ToModel, WithHeadingRow
{
    /**
     * @return Model|null
     */
    public function model(array $row)
    {
        return new Customer([
            'name' => $row['name'],
            'address' => $row['address'] ?? '',
            'phone_number' => $row['phone_number'] ?? '',
            'credit_limit' => $row['credit_limit'] ?? 0,
        ]);
    }
}
