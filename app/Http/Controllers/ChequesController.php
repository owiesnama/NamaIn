<?php

namespace App\Http\Controllers;

use App\Enums\ChequeStatus;
use App\Filters\ChequeFilter;
use App\Http\Requests\ChequeRequest;
use App\Models\Bank;
use App\Models\Cheque;
use App\Models\Customer;
use App\Models\Supplier;

class ChequesController extends Controller
{
    public function index(ChequeFilter $filter)
    {
        return inertia('Cheques/Index', [
            'initialCheques' => Cheque::with('payee')
                ->filter($filter)
                ->orderBy('type')
                ->oldest('due')
                ->orderBy('created_at')
                ->paginate(10),
            'status' => ChequeStatus::casesWithLabels(),
        ]);
    }

    public function create()
    {
        $customers = Customer::all()->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'type' => Customer::class,
            'type_string' => 'Customer',
        ]);

        $suppliers = Supplier::all()->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'type' => Supplier::class,
            'type_string' => 'Supplier',
        ]);

        $payees = $customers->concat($suppliers);

        return inertia('Cheques/Create', [
            'payees' => $payees,
            'banks' => Bank::orderBy('name')->get(),
        ]);
    }

    public function store(ChequeRequest $request)
    {
        Cheque::forPayee($request->get('payee_id'), $request->get('payee_type'))
            ->register([
                'type' => $request->get('type'),
                'amount' => $request->get('amount'),
                'bank_id' => $request->get('bank_id'),
                'due' => $request->get('due'),
                'reference_number' => $request->get('reference_number'),
            ]);

        return redirect()->route('cheques.index')->with('success', 'New cheque has been registered');
    }
}
