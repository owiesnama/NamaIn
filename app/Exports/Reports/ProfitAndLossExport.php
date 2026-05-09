<?php

namespace App\Exports\Reports;

use App\Exports\Concerns\WithExportStyles;
use App\Queries\Reports\ProfitAndLossQuery;
use App\Services\Utils\DatePreset;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;

class ProfitAndLossExport implements FromArray, WithHeadings, WithStyles
{
    use WithExportStyles;

    public function __construct(protected array $filters = []) {}

    public function array(): array
    {
        $request = new Request($this->filters);
        $dates = (new DatePreset)->fromRequest($request);

        return (new ProfitAndLossQuery)->get($dates['from'], $dates['to']);
    }

    public function headings(): array
    {
        return [__('Period'), __('Revenue'), __('COGS'), __('Gross Profit'), __('Expenses'), __('Net Profit')];
    }
}
