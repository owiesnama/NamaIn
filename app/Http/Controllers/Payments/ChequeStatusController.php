<?php

namespace App\Http\Controllers\Payments;

use App\Actions\UpdateChequeStatusAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateChequeStatusRequest;
use App\Models\Cheque;

class ChequeStatusController extends Controller
{
    public function update(
        Cheque $cheque,
        UpdateChequeStatusRequest $request,
        UpdateChequeStatusAction $updateChequeStatus,
    ) {
        $this->authorize('update', $cheque);

        $updateChequeStatus->handle(
            cheque: $cheque,
            status: $request->status(),
            clearedAmount: $request->clearedAmount(),
            treasuryAccountId: $request->treasuryAccountId(),
            actor: $request->user(),
        );

        return back()->with('success', 'Cheque status updated');
    }
}
