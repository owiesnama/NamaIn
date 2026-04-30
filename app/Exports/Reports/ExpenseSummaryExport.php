<?php

namespace App\Exports\Reports;

use App\Exports\Concerns\WithExportStyles;
use App\Queries\Reports\ExpenseSummaryQuery;
use App\Services\ReportDateResolver;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;

class ExpenseSummaryExport implements FromArray, WithHeadings, WithStyles
{
    use WithExportStyles;

    public function __construct(protected array $filters = []) {}

    public function array(): array
    {
        $request = new Request($this->filters);
        $dates = (new ReportDateResolver)->resolve($request);

        return (new ExpenseSummaryQuery)->getData($dates['from'], $dates['to']);
    }

    public function headings(): array
    {
        return [__('Category ID'), __('Category'), __('Total Spent'), __('Budget'), __('Variance'), __('Count')];
    }
}
