<?php

namespace App\Traits;

use App\Models\TreasuryAccount;
use App\Queries\PartyAccountQuery;
use App\Queries\StatementQuery;
use App\Services\StatementService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HandlesPartyAccount
{
    protected function handleAccount(Model $party, PartyAccountQuery $query)
    {
        $resourceName = Str::camel(Str::singular($this->inertiaFolder));

        $advances = method_exists($party, 'advances')
            ? $party->advances()->with('treasuryAccount')->latest()->get()
            : collect();

        return inertia("{$this->inertiaFolder}/Account", [
            $resourceName => $party,
            'account_balance' => $party->account_balance,
            'invoices' => $query->invoices($party, parent::ELEMENTS_PER_PAGE),
            'payments' => $query->payments($party, parent::ELEMENTS_PER_PAGE),
            'transactions' => $query->transactions($party, parent::ELEMENTS_PER_PAGE),
            'advances' => $advances,
            'treasury_accounts' => TreasuryAccount::active()->get(['id', 'name', 'type']),
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
}
