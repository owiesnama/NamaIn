<?php

namespace App\Exports\Reports;

use App\Queries\Reports\ProfitAndLossQuery;
use App\Services\ReportDateResolver;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProfitAndLossExport implements FromArray, WithHeadings
{
    public function __construct(protected array $filters = []) {}

    public function array(): array
    {
        $request = new Request($this->filters);
        $dates = (new ReportDateResolver)->resolve($request);

        return (new ProfitAndLossQuery)->getData($dates['from'], $dates['to']);
    }

    public function headings(): array
    {
        return ['Period', 'Revenue', 'COGS', 'Gross Profit', 'Expenses', 'Net Profit'];
    }
}
