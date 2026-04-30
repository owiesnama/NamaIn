<?php

namespace App\Exports;

use App\Filters\ExpenseFilter;
use App\Models\Expense;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ExpenseExport implements FromQuery, WithHeadings, WithMapping
{
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
            'ID',
            'Title',
            'Amount',
            'Currency',
            'Category',
            'Status',
            'Approved By',
            'Date',
            'Notes',
            'Created By',
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
