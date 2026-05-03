<?php

namespace App\Imports;

use App\Models\Customer;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Validators\Failure;

class QuickBooksCustomerImport implements OnEachRow, SkipsOnFailure, WithHeadingRow, WithMultipleSheets, WithValidation
{
    private int $rowCount = 0;

    /** @var Failure[] */
    private array $failures = [];

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

        $name = trim($data['customer'] ?? '');

        if ($name === '' || preg_match('/^[.\s]+$/', $name)) {
            return;
        }

        $balance = (float) ($data['balance'] ?? 0);

        Customer::create([
            'name' => $name,
            'address' => trim($data['bill_to_1'] ?? ''),
            'phone_number' => trim($data['phone'] ?? ''),
            'credit_limit' => (float) ($data['credit_limit'] ?? 0),
            'opening_debit' => $balance > 0 ? $balance : 0,
            'opening_credit' => $balance < 0 ? abs($balance) : 0,
        ]);

        $this->rowCount++;
    }

    public function rules(): array
    {
        return [
            'customer' => 'required|string',
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
