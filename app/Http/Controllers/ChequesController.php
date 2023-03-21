<?php

namespace App\Http\Controllers;

use App\Models\Cheque;
use App\Models\Customer;
use App\Models\Vendor;

class ChequesController extends Controller
{
    public function index()
    {
        return inertia('Cheques/Index', ['cheques' => Cheque::with('payee')
            ->search(request('search'))
            ->when(in_array(request('chequeType'), ['0', '1'], strict: true), fn ($query) => $query->where('type', request('chequeType')))
            ->orderBy('type')->oldest('due')->get()]);
    }

    public function create()
    {
        $payees = Customer::all()->concat(Vendor::all())->map(function ($payee) {
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

        return redirect()->route('cheques.index')->with('flash', ['message' => 'new cheque has been registered']);
    }
}
