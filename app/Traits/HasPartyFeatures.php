<?php

namespace App\Traits;

use App\Queries\PartyAccountQuery;
use App\Queries\StatementQuery;
use App\Services\CsvSampleGenerator;
use App\Services\StatementService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

trait HasPartyFeatures
{
    protected function handleAccount(Model $party, PartyAccountQuery $query)
    {
        $resourceName = Str::camel(Str::singular($this->inertiaFolder));

        return inertia("{$this->inertiaFolder}/Account", [
            $resourceName => $party,
            'account_balance' => $party->account_balance,
            'invoices' => $query->invoices($party, parent::ELEMENTS_PER_PAGE),
            'payments' => $query->payments($party, parent::ELEMENTS_PER_PAGE),
            'transactions' => $query->transactions($party, parent::ELEMENTS_PER_PAGE),
        ]);
    }

    protected function handleStatement(Model $party, StatementQuery $query)
    {
        $startDate = request('start_date', now()->subMonth()->toDateString());
        $endDate = request('end_date', now()->toDateString());

        $data = $query->forParty($party, $startDate, $endDate);

        $resourceName = Str::camel(Str::singular($this->inertiaFolder));

        return inertia("{$this->inertiaFolder}/Statement", array_merge([
            $resourceName => $party,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ], $data));
    }

    protected function handlePrintStatement(Model $party, StatementService $statementService)
    {
        $startDate = request('start_date', now()->subMonth()->toDateTimeString());
        $endDate = request('end_date', now()->toDateTimeString());

        $data = (new StatementQuery)->forParty($party, $startDate, $endDate);

        $pdf = $statementService->generatePdf($party, $data, $startDate, $endDate);

        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename='statement-{$party->name}.pdf'",
        ];

        return response(content: $pdf, headers: $headers);
    }

    public function import()
    {
        Excel::import(new $this->importClass, request()->file('file'));

        return back()->with('success', 'Imported successfully');
    }

    public function importSample(): BinaryFileResponse
    {
        $filename = Str::plural(Str::snake(class_basename($this->model))).'_import_sample.csv';

        return (new CsvSampleGenerator)->generate($filename, $this->importHeaders, $this->importSampleData);
    }

    public function export()
    {
        return Excel::download(new $this->exportClass, Str::plural(Str::snake(class_basename($this->model))).'.xlsx');
    }
}
