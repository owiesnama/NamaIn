<?php

namespace App\Exports\Reports;

use App\Exports\Concerns\WithExportStyles;
use App\Queries\Reports\TreasuryReconciliationQuery;
use App\Services\ReportDateResolver;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;

class TreasuryReconciliationExport implements FromArray, WithHeadings, WithStyles
{
    use WithExportStyles;

    public function __construct(protected array $filters = []) {}

    public function array(): array
    {
        $request = new Request($this->filters);
        $dates = (new ReportDateResolver)->resolve($request);
        $accountId = isset($this->filters['treasury_account']) ? (int) $this->filters['treasury_account'] : null;

        return (new TreasuryReconciliationQuery)->getData($dates['from'], $dates['to'], $accountId);
    }

    public function headings(): array
    {
        return [__('ID'), __('Account'), __('Date'), __('Reason'), __('Amount'), __('Balance After')];
    }
}
