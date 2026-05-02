<?php

namespace App\Imports;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CustomerImport implements ToModel, WithHeadingRow
{
    private int $rowCount = 0;

    /**
     * @return Model|null
     */
    public function model(array $row)
    {
        $this->rowCount++;

        return new Customer([
            'name' => $row['name'],
            'address' => $row['address'] ?? '',
            'phone_number' => $row['phone_number'] ?? '',
            'credit_limit' => $row['credit_limit'] ?? 0,
            'opening_debit' => $row['opening_debit'] ?? 0,
            'opening_credit' => $row['opening_credit'] ?? 0,
        ]);
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }
}
