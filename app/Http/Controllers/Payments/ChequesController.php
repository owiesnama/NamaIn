<?php

namespace App\Http\Controllers\Payments;

use App\Actions\RegisterChequeAction;
use App\Actions\UpdateChequeAction;
use App\Enums\ChequeStatus;
use App\Enums\TreasuryAccountType;
use App\Exceptions\ChequeClearingAccountRequired;
use App\Filters\ChequeFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChequeRequest;
use App\Models\Bank;
use App\Models\Cheque;
use App\Models\TreasuryAccount;
use App\Queries\ChequeIndexQuery;
use App\Queries\ChequePayeeLookupQuery;

class ChequesController extends Controller
{
    public function index(ChequeFilter $filter, ChequeIndexQuery $query)
    {
        $this->authorize('viewAny', Cheque::class);

        return inertia('Cheques/Index', [
            'initialCheques' => $query->paginated($filter, request('sort_by'), request('sort_order', 'asc')),
            'status' => ChequeStatus::casesWithLabels(),
            'bank_treasury_accounts' => TreasuryAccount::ofType(TreasuryAccountType::Bank)->active()->get(['id', 'name', 'bank_id']),
            'summary' => $query->summary(),
        ]);
    }

    public function create(ChequePayeeLookupQuery $payees)
    {
        $this->authorize('create', Cheque::class);

        return inertia('Cheques/Create', [
            'payees' => $payees->all(),
            'banks' => Bank::orderBy('name')->get(),
        ]);
    }

    public function store(ChequeRequest $request, RegisterChequeAction $registerCheque)
    {
        $this->authorize('create', Cheque::class);

        try {
            $registerCheque->handle($request->validated(), $request->user());
        } catch (ChequeClearingAccountRequired $exception) {
            return redirect()->route('treasury.create')->with('error', $exception->getMessage());
        }

        return redirect()->route('cheques.index')->with('success', __('New cheque registered successfully'));
    }

    public function edit(Cheque $cheque, ChequePayeeLookupQuery $payees)
    {
        $this->authorize('update', $cheque);

        if (! $cheque->isEditable()) {
            return redirect()->route('cheques.index')->with('error', 'This cheque can only be modified while in Drafted status.');
        }

        return inertia('Cheques/Edit', [
            'cheque' => $cheque,
            'payees' => $payees->all(),
            'banks' => Bank::orderBy('name')->get(),
            'invoices' => $payees->outstandingInvoicesFor($cheque->chequeable_id, $cheque->chequeable_type),
        ]);
    }

    public function update(Cheque $cheque, ChequeRequest $request, UpdateChequeAction $updateCheque)
    {
        $this->authorize('update', $cheque);

        if (! $cheque->isEditable()) {
            abort(403, 'This cheque can only be modified while in Drafted status.');
        }

        $updateCheque->handle($cheque, $request->validated());

        return redirect()->route('cheques.index')->with('success', __('Cheque updated successfully'));
    }

    public function destroy(Cheque $cheque)
    {
        $this->authorize('delete', $cheque);

        if (! $cheque->isDeletable()) {
            return redirect()->route('cheques.index')->with('error', 'Only Drafted or Cancelled cheques can be deleted.');
        }

        $cheque->delete();

        return redirect()->route('cheques.index')->with('success', __('Cheque deleted successfully'));
    }
}
