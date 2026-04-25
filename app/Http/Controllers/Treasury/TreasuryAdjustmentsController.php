<?php

namespace App\Http\Controllers\Treasury;

use App\Actions\Treasury\RecordTreasuryAdjustmentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\TreasuryAdjustmentRequest;
use App\Models\TreasuryAccount;

class TreasuryAdjustmentsController extends Controller
{
    public function store(TreasuryAdjustmentRequest $request, TreasuryAccount $treasury, RecordTreasuryAdjustmentAction $action)
    {
        $this->authorize('adjust', TreasuryAccount::class);

        $action->handle(
            account: $treasury,
            newBalance: $request->new_balance,
            notes: $request->notes,
            actor: auth()->user(),
        );

        return back()->with('success', 'Balance adjusted successfully.');
    }
}
