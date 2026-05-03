<?php

namespace App\Imports;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;

class CustomerImport implements SkipsOnFailure, ToModel, WithHeadingRow, WithValidation
{
    private int $rowCount = 0;

    /** @var Failure[] */
    private array $failures = [];

    public function model(array $row): ?Model
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

    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'credit_limit' => 'nullable|numeric|min:0',
            'opening_debit' => 'nullable|numeric|min:0',
            'opening_credit' => 'nullable|numeric|min:0',
        ];
    }

    public function onFailure(Failure ...$failures): void
    {
        foreach ($failures as $failure) {
            $this->failures[] = $failure;
        }
    }

    /** @return Failure[] */
    public function failures(): array
    {
        return $this->failures;
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }
}
