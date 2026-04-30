<?php

namespace App\Exports;

use App\Exports\Concerns\WithExportStyles;
use App\Filters\ExpenseFilter;
use App\Models\Expense;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;

class ExpenseExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    use WithExportStyles;

    protected ExpenseFilter $filter;

    public function __construct(ExpenseFilter|array $filters = [])
    {
        if ($filters instanceof ExpenseFilter) {
            $this->filter = $filters;
        } else {
            $this->filter = app(ExpenseFilter::class);
        }
    }

    public function query()
    {
        return Expense::filter($this->filter)
            ->with(['categories', 'createdBy', 'approvedBy'])
            ->latest();
    }

    public function headings(): array
    {
        return [
            __('ID'),
            __('Title'),
            __('Amount'),
            __('Currency'),
            __('Category'),
            __('Status'),
            __('Approved By'),
            __('Date'),
            __('Notes'),
            __('Created By'),
        ];
    }

    public function map($expense): array
    {
        return [
            $expense->id,
            $expense->title,
            $expense->amount,
            $expense->currency,
            $expense->categories->pluck('name')->implode(', '),
            $expense->status->value,
            $expense->approvedBy?->name,
            $expense->expensed_at->format('Y-m-d'),
            $expense->notes,
            $expense->createdBy?->name,
        ];
    }
}
