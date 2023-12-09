<?php

namespace App\Http\Controllers;

use App\Enums\ChequeStatus;
use App\Filters\ChequeFilter;
use App\Http\Requests\ChequeRequest;
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
        $payees = Customer::all()->concat(
            Supplier::all()->toArray()
        );

        return inertia('Cheques/Create', [
            'payees' => $payees,
        ]);
    }

    public function store(ChequeRequest $request)
    {
        Cheque::forPayee($request->get('payee'))
            ->register([
                'type' => $request->get('type'),
                'amount' => $request->get('amount'),
                'bank' => $request->get('bank'),
                'due' => $request->get('due'),
            ]);

        return redirect()->route('cheques.index')->with('success', 'New cheque has been registered');
    }
}
