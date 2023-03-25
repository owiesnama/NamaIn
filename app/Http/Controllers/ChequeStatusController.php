<?php

namespace App\Http\Controllers;

use App\Enums\ChequeStatus;
use App\Models\Cheque;
use Illuminate\Validation\Rules\Enum;

class ChequeStatusController extends Controller
{
    public function update(Cheque $cheque)
    {
        request()->validate(['status' => ['required', new Enum(ChequeStatus::class)]]);
        $cheque->status = ChequeStatus::from(request('status'));
        $cheque->save();

        return back()->with('flash', ['message' => 'Cheque status updated']);
    }
}
