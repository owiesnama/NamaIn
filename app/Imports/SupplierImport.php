<?php

namespace App\Imports;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class SupplierImport implements ToModel, WithHeadingRow
{
    private int $rowCount = 0;

    /**
     * @return Model|null
     */
    public function model(array $row)
    {
        $this->rowCount++;

        return new Supplier([
            'name' => $row['name'],
            'address' => $row['address'] ?? '',
            'phone_number' => $row['phone_number'] ?? '',
            'opening_debit' => $row['opening_debit'] ?? 0,
            'opening_credit' => $row['opening_credit'] ?? 0,
        ]);
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }
}
