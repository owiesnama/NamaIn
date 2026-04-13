<?php

namespace App\Exports;

use App\Models\Customer;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CustomerExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @return Collection
     */
    public function collection()
    {
        return Customer::with('categories')->get();
    }

    public function headings(): array
    {
        return [
            'name',
            'address',
            'phone_number',
            'credit_limit',
            'categories',
        ];
    }

    public function map($customer): array
    {
        return [
            $customer->name,
            $customer->address,
            $customer->phone_number,
            $customer->credit_limit,
            $customer->categories->pluck('name')->implode(','),
        ];
    }
}
