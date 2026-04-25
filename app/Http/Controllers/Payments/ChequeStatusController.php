<?php

namespace App\Http\Controllers\Payments;

use App\Actions\ClearCheque;
use App\Actions\Treasury\RecordTreasuryMovementAction;
use App\Enums\ChequeStatus;
use App\Enums\TreasuryAccountType;
use App\Enums\TreasuryMovementReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateChequeStatusRequest;
use App\Models\Cheque;
use App\Models\TreasuryAccount;

class ChequeStatusController extends Controller
{
    public function update(
        Cheque $cheque,
        ClearCheque $clearCheque,
        RecordTreasuryMovementAction $recordMovement,
        UpdateChequeStatusRequest $request,
    ) {
        $status = ChequeStatus::from($request->validated('status'));

        if ($status === ChequeStatus::Cleared || $status === ChequeStatus::PartiallyCleared) {
            $clearCheque->handle($cheque, $request->validated('cleared_amount'), $request->validated('treasury_account_id'));
        } elseif ($status === ChequeStatus::Returned && $cheque->isReceivable()) {
            // Reverse the cheque_clearing credit recorded when the cheque was registered
            $clearingAccount = TreasuryAccount::ofType(TreasuryAccountType::ChequeClearing)->active()->first();

            if ($clearingAccount) {
                $recordMovement->handle(
                    account: $clearingAccount,
                    amount: -(int) round($cheque->amount * 100),
                    reason: TreasuryMovementReason::ChequeBounced,
                    movable: $cheque,
                    actor: auth()->user(),
                );
            }

            $cheque->status = $status;
            $cheque->save();
        } else {
            $cheque->status = $status;
            $cheque->save();
        }

        return back()->with('success', 'Cheque status updated');
    }
}
