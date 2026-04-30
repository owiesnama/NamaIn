<?php

namespace App\Exports;

use App\Exports\Concerns\WithExportStyles;
use App\Models\Customer;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;

class CustomerExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    use WithExportStyles;

    public function __construct(protected array $filters = []) {}

    public function collection(): Collection
    {
        return Customer::with('categories')->get();
    }

    public function headings(): array
    {
        return [
            __('Name'),
            __('Address'),
            __('Phone Number'),
            __('Credit Limit'),
            __('Categories'),
        ];
    }

    public function map($customer): array
    {
        return [
            $customer->name,
            $customer->address,
            $customer->phone_number,
            $customer->credit_limit,
            $customer->categories->pluck('name')->implode(', '),
        ];
    }
}
