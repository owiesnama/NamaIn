<?php

namespace App\Http\Controllers;

use App\Enums\ChequeStatus;
use App\Filters\ChequeFilter;
use App\Models\Cheque;
use App\Models\Customer;
use App\Models\Supplier;

class ChequesController extends Controller
{
    public function index(ChequeFilter $filter)
    {
        return inertia('Cheques/Index', [
            'initialCheques' => Cheque::with('payee')
                ->filterUsing($filter)
                ->search(request('search'))
                ->orderBy('type')
                ->oldest('due')
                ->orderBy('created_at')
                ->paginate(10),
            'status' => ChequeStatus::casesWithLabels(),
        ]);
    }

    public function create()
    {
        $payees = Customer::all()->concat(Supplier::all())->map(function ($payee) {
            $payee->type = get_class($payee);
            $payee->type_string = class_basename($payee);

            return $payee;
        });

        return inertia('Cheques/Create', [
            'payees' => $payees,
        ]);
    }

    public function store()
    {
        Cheque::forPayee(request('payee'))->register([
            'type' => request('type'),
            'amount' => request('amount'),
            'due' => request('due'),
        ]);

        return redirect()->route('cheques.index')->with('success', 'New cheque has been registered');
    }
}
