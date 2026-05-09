<?php

namespace App\Exports\Reports;

use App\Exports\Concerns\WithExportStyles;
use App\Queries\Reports\TreasuryReconciliationQuery;
use App\Services\Utils\DatePreset;
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
        $dates = (new DatePreset)->fromRequest($request);
        $accountId = isset($this->filters['treasury_account']) ? (int) $this->filters['treasury_account'] : null;

        return (new TreasuryReconciliationQuery)->get($dates['from'], $dates['to'], $accountId);
    }

    public function headings(): array
    {
        return [__('ID'), __('Account'), __('Date'), __('Reason'), __('Amount'), __('Balance After')];
    }
}
