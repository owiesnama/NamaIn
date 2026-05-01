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

        try {
            $updateChequeStatus->handle(
                cheque: $cheque,
                status: $request->status(),
                clearedAmount: $request->clearedAmount(),
                treasuryAccountId: $request->treasuryAccountId(),
                actor: $request->user(),
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', __('Cannot clear cheque: no bank treasury account is linked. Please link a treasury account to this bank or select one manually.'));
        }

        return back()->with('success', __('Cheque status updated'));
    }
}
