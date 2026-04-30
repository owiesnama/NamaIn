<?php

namespace App\Exports\Reports;

use App\Queries\Reports\PosSessionReportQuery;
use App\Services\ReportDateResolver;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PosSessionExport implements FromArray, WithHeadings
{
    public function __construct(protected array $filters = []) {}

    public function array(): array
    {
        $request = new Request($this->filters);
        $dates = (new ReportDateResolver)->resolve($request);

        return (new PosSessionReportQuery)->getData($dates['from'], $dates['to']);
    }

    public function headings(): array
    {
        return ['ID', 'Operator', 'Storage', 'Opened At', 'Closed At', 'Opening Float', 'Cash Sales', 'Expected Close', 'Closing Float', 'Variance', 'Invoices'];
    }
}
