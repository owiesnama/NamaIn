<?php

namespace App\Http\Controllers;

use App\Actions\ClearCheque;
use App\Enums\ChequeStatus;
use App\Http\Requests\UpdateChequeStatusRequest;
use App\Models\Cheque;

class ChequeStatusController extends Controller
{
    public function update(Cheque $cheque, ClearCheque $clearCheque, UpdateChequeStatusRequest $request)
    {
        $status = ChequeStatus::from($request->validated('status'));

        if ($status === ChequeStatus::Cleared || $status === ChequeStatus::PartiallyCleared) {
            $clearCheque->handle($cheque, $request->validated('cleared_amount'));
        } else {
            $cheque->status = $status;
            $cheque->save();
        }

        return back()->with('success', 'Cheque status updated');
    }
}
