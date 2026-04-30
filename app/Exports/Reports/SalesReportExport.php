<?php

namespace App\Exports\Reports;

use App\Queries\Reports\SalesReportQuery;
use App\Services\ReportDateResolver;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SalesReportExport implements FromArray, WithHeadings
{
    public function __construct(protected array $filters = []) {}

    public function array(): array
    {
        $request = new Request($this->filters);
        $dates = (new ReportDateResolver)->resolve($request);
        $groupBy = $this->filters['group_by'] ?? 'day';

        return (new SalesReportQuery)->getData($dates['from'], $dates['to'], $groupBy);
    }

    public function headings(): array
    {
        return ['Period', 'Invoices', 'Items Sold', 'Revenue', 'Avg Order Value'];
    }
}
