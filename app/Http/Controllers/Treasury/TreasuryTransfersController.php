<?php

namespace App\Http\Controllers\Treasury;

use App\Actions\Treasury\ExecuteTreasuryTransferAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\TreasuryTransferRequest;
use App\Models\TreasuryAccount;
use App\Models\TreasuryTransfer;

class TreasuryTransfersController extends Controller
{
    public function create()
    {
        $this->authorize('transfer', TreasuryAccount::class);

        return inertia('Treasury/Transfer/Create', [
            'accounts' => TreasuryAccount::active()
                ->get()
                ->map(fn (TreasuryAccount $account) => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'type_label' => $account->type->label(),
                    'currency' => $account->currency,
                    'current_balance' => $account->currentBalance(),
                ]),
        ]);
    }

    public function store(TreasuryTransferRequest $request, ExecuteTreasuryTransferAction $action)
    {
        $this->authorize('transfer', TreasuryAccount::class);

        $from = TreasuryAccount::findOrFail($request->from_account_id);
        $to = TreasuryAccount::findOrFail($request->to_account_id);

        $transfer = $action->handle(
            from: $from,
            to: $to,
            amount: $request->amount,
            actor: auth()->user(),
            notes: $request->notes,
        );

        return redirect()->route('treasury.show', $from)->with('success', 'Transfer completed successfully.');
    }

    public function show(int $transfer)
    {
        $this->authorize('view', TreasuryAccount::class);

        $tenantAccountIds = TreasuryAccount::pluck('id');

        $transfer = TreasuryTransfer::where(function ($query) use ($tenantAccountIds) {
            $query->whereIn('from_account_id', $tenantAccountIds)
                ->orWhereIn('to_account_id', $tenantAccountIds);
        })->findOrFail($transfer);

        return inertia('Treasury/Transfer/Show', [
            'transfer' => $transfer->load(['fromAccount', 'toAccount', 'createdBy']),
        ]);
    }
}
