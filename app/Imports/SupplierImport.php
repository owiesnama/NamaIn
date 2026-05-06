<?php

namespace App\Imports;

use App\Imports\Concerns\NormalizesArabicEncoding;
use App\Imports\Concerns\TracksImportProgress;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class SupplierImport implements SkipsOnFailure, ToModel, WithHeadingRow, WithValidation
{
    use NormalizesArabicEncoding;
    use TracksImportProgress;

    public function model(array $row): ?Model
    {
        $this->incrementRowCount();

        return new Supplier([
            'name' => $this->normalizeText($row['name']),
            'address' => $this->normalizeText($row['address'] ?? '') ?? '',
            'phone_number' => $row['phone_number'] ?? '',
            'opening_debit' => $row['opening_debit'] ?? 0,
            'opening_credit' => $row['opening_credit'] ?? 0,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'opening_debit' => 'nullable|numeric|min:0',
            'opening_credit' => 'nullable|numeric|min:0',
        ];
    }
}
